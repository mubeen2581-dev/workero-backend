<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadDistributionService
{
    /**
     * Distribute leads using round-robin algorithm
     * Assigns leads evenly across available users
     */
    public function distributeRoundRobin(array $leadIds, ?string $role = null): array
    {
        $companyId = auth()->user()->company_id;
        
        // Get eligible users (by role if specified, otherwise all CRM users)
        $query = User::where('company_id', $companyId)
            ->whereIn('role', ['admin', 'manager', 'dispatcher']);
        
        if ($role) {
            $query->where('role', $role);
        }
        
        $users = $query->get();
        
        if ($users->isEmpty()) {
            throw new \Exception('No eligible users found for lead distribution');
        }
        
        // Get current lead counts per user to balance distribution
        $leadCounts = Lead::where('company_id', $companyId)
            ->whereIn('assigned_to', $users->pluck('id'))
            ->select('assigned_to', DB::raw('count(*) as count'))
            ->groupBy('assigned_to')
            ->pluck('count', 'assigned_to')
            ->toArray();
        
        // Initialize counts for users with no leads
        foreach ($users as $user) {
            if (!isset($leadCounts[$user->id])) {
                $leadCounts[$user->id] = 0;
            }
        }
        
        // Sort users by lead count (ascending) to balance workload
        $users = $users->sortBy(function ($user) use ($leadCounts) {
            return $leadCounts[$user->id] ?? 0;
        })->values();
        
        $assignments = [];
        $userIndex = 0;
        
        foreach ($leadIds as $leadId) {
            $lead = Lead::where('company_id', $companyId)->findOrFail($leadId);
            
            // Skip if already assigned
            if ($lead->assigned_to) {
                continue;
            }
            
            // Assign to user with least leads
            $assignedUser = $users[$userIndex % $users->count()];
            $lead->assigned_to = $assignedUser->id;
            $lead->save();
            
            // Update local count
            $leadCounts[$assignedUser->id] = ($leadCounts[$assignedUser->id] ?? 0) + 1;
            
            // Re-sort users for next assignment
            $users = $users->sortBy(function ($user) use ($leadCounts) {
                return $leadCounts[$user->id] ?? 0;
            })->values();
            
            $assignments[] = [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUser->id,
                'user_name' => "{$assignedUser->first_name} {$assignedUser->last_name}",
            ];
            
            $userIndex++;
        }
        
        return $assignments;
    }
    
    /**
     * Distribute leads based on workload (least busy users first)
     */
    public function distributeByWorkload(array $leadIds, ?string $role = null): array
    {
        $companyId = auth()->user()->company_id;
        
        // Get eligible users
        $query = User::where('company_id', $companyId)
            ->whereIn('role', ['admin', 'manager', 'dispatcher']);
        
        if ($role) {
            $query->where('role', $role);
        }
        
        $users = $query->get();
        
        if ($users->isEmpty()) {
            throw new \Exception('No eligible users found for lead distribution');
        }
        
        // Count active leads per user (leads not in 'converted' or 'lost' status)
        $workloads = Lead::where('company_id', $companyId)
            ->whereIn('assigned_to', $users->pluck('id'))
            ->whereNotIn('status', ['converted', 'lost'])
            ->select('assigned_to', DB::raw('count(*) as count'))
            ->groupBy('assigned_to')
            ->pluck('count', 'assigned_to')
            ->toArray();
        
        // Initialize workloads for users with no active leads
        foreach ($users as $user) {
            if (!isset($workloads[$user->id])) {
                $workloads[$user->id] = 0;
            }
        }
        
        $assignments = [];
        
        foreach ($leadIds as $leadId) {
            $lead = Lead::where('company_id', $companyId)->findOrFail($leadId);
            
            // Skip if already assigned
            if ($lead->assigned_to) {
                continue;
            }
            
            // Find user with least workload
            $assignedUserId = array_keys($workloads, min($workloads))[0];
            $assignedUser = $users->firstWhere('id', $assignedUserId);
            
            $lead->assigned_to = $assignedUserId;
            $lead->save();
            
            // Update workload
            $workloads[$assignedUserId]++;
            
            $assignments[] = [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUser->id,
                'user_name' => "{$assignedUser->first_name} {$assignedUser->last_name}",
                'workload' => $workloads[$assignedUserId],
            ];
        }
        
        return $assignments;
    }
    
    /**
     * Distribute leads based on priority and user capacity
     */
    public function distributeByPriority(array $leadIds, ?string $role = null): array
    {
        $companyId = auth()->user()->company_id;
        
        // Get eligible users
        $query = User::where('company_id', $companyId)
            ->whereIn('role', ['admin', 'manager', 'dispatcher']);
        
        if ($role) {
            $query->where('role', $role);
        }
        
        $users = $query->get();
        
        if ($users->isEmpty()) {
            throw new \Exception('No eligible users found for lead distribution');
        }
        
        // Get leads with their priorities
        $leads = Lead::where('company_id', $companyId)
            ->whereIn('id', $leadIds)
            ->whereNull('assigned_to')
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->get();
        
        // Count active leads per user
        $workloads = Lead::where('company_id', $companyId)
            ->whereIn('assigned_to', $users->pluck('id'))
            ->whereNotIn('status', ['converted', 'lost'])
            ->select('assigned_to', DB::raw('count(*) as count'))
            ->groupBy('assigned_to')
            ->pluck('count', 'assigned_to')
            ->toArray();
        
        // Initialize workloads
        foreach ($users as $user) {
            if (!isset($workloads[$user->id])) {
                $workloads[$user->id] = 0;
            }
        }
        
        $assignments = [];
        
        foreach ($leads as $lead) {
            // Find user with least workload
            $assignedUserId = array_keys($workloads, min($workloads))[0];
            $assignedUser = $users->firstWhere('id', $assignedUserId);
            
            $lead->assigned_to = $assignedUserId;
            $lead->save();
            
            // Update workload
            $workloads[$assignedUserId]++;
            
            $assignments[] = [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUser->id,
                'user_name' => "{$assignedUser->first_name} {$assignedUser->last_name}",
                'priority' => $lead->priority,
                'workload' => $workloads[$assignedUserId],
            ];
        }
        
        return $assignments;
    }
    
    /**
     * Get user workload statistics
     */
    public function getUserWorkloads(?string $role = null): array
    {
        $companyId = auth()->user()->company_id;
        
        $query = User::where('company_id', $companyId)
            ->whereIn('role', ['admin', 'manager', 'dispatcher']);
        
        if ($role) {
            $query->where('role', $role);
        }
        
        $users = $query->get();
        
        $workloads = [];
        
        foreach ($users as $user) {
            $totalLeads = Lead::where('company_id', $companyId)
                ->where('assigned_to', $user->id)
                ->count();
            
            $activeLeads = Lead::where('company_id', $companyId)
                ->where('assigned_to', $user->id)
                ->whereNotIn('status', ['converted', 'lost'])
                ->count();
            
            $urgentLeads = Lead::where('company_id', $companyId)
                ->where('assigned_to', $user->id)
                ->where('priority', 'urgent')
                ->whereNotIn('status', ['converted', 'lost'])
                ->count();
            
            $workloads[] = [
                'user_id' => $user->id,
                'user_name' => "{$user->first_name} {$user->last_name}",
                'role' => $user->role,
                'total_leads' => $totalLeads,
                'active_leads' => $activeLeads,
                'urgent_leads' => $urgentLeads,
            ];
        }
        
        return $workloads;
    }
}

