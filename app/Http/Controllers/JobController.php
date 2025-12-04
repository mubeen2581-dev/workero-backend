<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Client;
use App\Models\Quote;
use App\Models\JobActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        // Debug logging
        \Log::info('JobController::index - Company ID:', ['company_id' => $companyId, 'user_id' => auth()->id()]);
        
        if (!$companyId) {
            \Log::warning('JobController::index - No company_id found for user', ['user_id' => auth()->id()]);
            return $this->error('Company ID not found', null, 400);
        }
        
        $query = Job::where('company_id', $companyId)->with('client', 'technician', 'quote');
        
        // Debug: Count total jobs for this company
        $totalJobs = $query->count();
        \Log::info('JobController::index - Total jobs for company', ['company_id' => $companyId, 'count' => $totalJobs]);
        
        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }
        
        // Filter by priority
        if ($request->has('priority') && $request->priority !== '') {
            $query->where('priority', $request->priority);
        }
        
        // Filter by assigned technician
        if ($request->has('assigned_technician') && $request->assigned_technician !== '') {
            $query->where('assigned_technician', $request->assigned_technician);
        }
        
        // Filter by client
        if ($request->has('client_id') && $request->client_id !== '') {
            $query->where('client_id', $request->client_id);
        }
        
        // Search
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('client', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }
        
        // Sort
        $sortField = $request->input('sort_field', 'scheduled_date');
        $sortDirection = $request->input('sort_direction', 'asc');
        
        $allowedSortFields = ['scheduled_date', 'created_at', 'priority', 'status'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }
        
        $perPage = $request->input('per_page', 10);
        $jobs = $query->paginate($perPage);
        
        return $this->paginated($jobs->items(), [
            'page' => $jobs->currentPage(),
            'limit' => $jobs->perPage(),
            'total' => $jobs->total(),
            'totalPages' => $jobs->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|uuid|exists:clients,id',
            'quote_id' => 'nullable|uuid|exists:quotes,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'estimated_duration' => 'nullable|numeric|min:0',
            'assigned_technician' => 'nullable|uuid|exists:users,id',
            'scheduled_date' => 'required|date',
            'location' => 'nullable|array',
            'materials' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        // Verify client belongs to company
        $client = Client::where('company_id', $companyId)
            ->findOrFail($request->input('client_id'));

        // Verify quote belongs to company and client if provided
        if ($request->has('quote_id')) {
            $quote = Quote::where('company_id', $companyId)
                ->where('client_id', $client->id)
                ->findOrFail($request->input('quote_id'));
        }

        // Verify technician belongs to company if provided
        if ($request->has('assigned_technician')) {
            $technician = \App\Models\User::where('company_id', $companyId)
                ->findOrFail($request->input('assigned_technician'));
        }

        DB::beginTransaction();
        try {
            // Get location from client if not provided
            $location = $request->input('location');
            if (!$location && $client->address) {
                $location = $client->address;
            }

            $job = Job::create([
                'company_id' => $companyId,
                'client_id' => $client->id,
                'quote_id' => $request->input('quote_id'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'status' => $request->input('status', 'scheduled'),
                'priority' => $request->input('priority', 'medium'),
                'estimated_duration' => $request->input('estimated_duration'),
                'assigned_technician' => $request->input('assigned_technician'),
                'scheduled_date' => $request->input('scheduled_date'),
                'location' => $location ?? [],
                'materials' => $request->input('materials'),
                'notes' => $request->input('notes'),
            ]);

            // Create activity log
            JobActivity::create([
                'job_id' => $job->id,
                'user_id' => auth()->id(),
                'type' => 'created',
                'description' => 'Job created',
                'metadata' => [
                    'status' => $job->status,
                    'priority' => $job->priority,
                    'title' => $job->title,
                ],
            ]);

            DB::commit();

            return $this->success(
                $job->fresh()->load('client', 'technician', 'quote')->toArray(),
                'Job created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create job: ' . $e->getMessage(), null, 500);
        }
    }

    public function show($id)
    {
        $companyId = $this->getCompanyId();
        $job = Job::where('company_id', $companyId)
            ->with('client', 'technician', 'quote', 'activities.user')
            ->findOrFail($id);
        
        return $this->success($job->toArray());
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $job = Job::where('company_id', $companyId)->findOrFail($id);

        // Prevent updates to completed or cancelled jobs
        if (in_array($job->status, ['completed', 'cancelled'])) {
            return $this->error('Cannot update completed or cancelled jobs', null, 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'estimated_duration' => 'nullable|numeric|min:0',
            'actual_duration' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'labor_cost' => 'nullable|numeric|min:0',
            'material_cost' => 'nullable|numeric|min:0',
            'profit_margin' => 'nullable|numeric',
            'assigned_technician' => 'nullable|uuid|exists:users,id',
            'scheduled_date' => 'sometimes|date',
            'location' => 'nullable|array',
            'materials' => 'nullable|array',
            'photos' => 'nullable|array',
            'notes' => 'nullable|string',
            'signature' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        // Verify technician belongs to company if provided
        if ($request->has('assigned_technician')) {
            $technician = \App\Models\User::where('company_id', $companyId)
                ->findOrFail($request->input('assigned_technician'));
        }

        DB::beginTransaction();
        try {
            $oldStatus = $job->status;
            $oldAssignedTechnician = $job->assigned_technician;

            $job->fill($request->only([
                'title',
                'description',
                'status',
                'priority',
                'estimated_duration',
                'actual_duration',
                'assigned_technician',
                'scheduled_date',
                'location',
                'materials',
                'photos',
                'notes',
                'signature',
            ]));

            // If status is being changed to completed, set completed_date
            if ($request->has('status') && $request->status === 'completed' && !$job->completed_date) {
                $job->completed_date = now();
            }

            $job->save();

            // Create activity logs for changes
            if ($request->has('status') && $oldStatus !== $job->status) {
                JobActivity::create([
                    'job_id' => $job->id,
                    'user_id' => auth()->id(),
                    'type' => 'status_changed',
                    'description' => "Status changed from {$oldStatus} to {$job->status}",
                    'metadata' => [
                        'old_status' => $oldStatus,
                        'new_status' => $job->status,
                    ],
                ]);
            }

            if ($request->has('assigned_technician') && $oldAssignedTechnician !== $job->assigned_technician) {
                $technician = $job->technician;
                JobActivity::create([
                    'job_id' => $job->id,
                    'user_id' => auth()->id(),
                    'type' => 'assigned',
                    'description' => $job->assigned_technician 
                        ? ($technician ? "Assigned to {$technician->first_name} {$technician->last_name}" : "Assigned to technician")
                        : 'Unassigned',
                    'metadata' => [
                        'old_assigned_technician' => $oldAssignedTechnician,
                        'new_assigned_technician' => $job->assigned_technician,
                    ],
                ]);
            }

            if ($request->has('notes') && !empty($request->input('notes'))) {
                JobActivity::create([
                    'job_id' => $job->id,
                    'user_id' => auth()->id(),
                    'type' => 'note_added',
                    'description' => 'Note added',
                    'metadata' => [
                        'note' => $request->input('notes'),
                    ],
                ]);
            }

            // Generic update activity if other fields changed
            if (count($request->only(['title', 'description', 'priority', 'estimated_duration', 'scheduled_date', 'location', 'materials', 'photos', 'signature'])) > 0 
                && !$request->has('status') && !$request->has('assigned_technician') && !$request->has('notes')) {
                JobActivity::create([
                    'job_id' => $job->id,
                    'user_id' => auth()->id(),
                    'type' => 'updated',
                    'description' => 'Job updated',
                    'metadata' => $request->only(['title', 'description', 'priority', 'estimated_duration', 'scheduled_date']),
                ]);
            }

            DB::commit();

            return $this->success(
                $job->fresh()->load('client', 'technician', 'quote')->toArray(),
                'Job updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update job: ' . $e->getMessage(), null, 500);
        }
    }

    public function destroy($id)
    {
        $companyId = $this->getCompanyId();
        $job = Job::where('company_id', $companyId)->findOrFail($id);
        
        // Prevent deletion of in-progress or completed jobs
        if (in_array($job->status, ['in_progress', 'completed'])) {
            return $this->error('Cannot delete in-progress or completed jobs', null, 422);
        }
        
        $job->delete();
        
        return $this->success(null, 'Job deleted successfully');
    }

    public function assign(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $job = Job::where('company_id', $companyId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'assigned_technician' => 'required|uuid|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        // Verify technician belongs to company
        $technician = \App\Models\User::where('company_id', $companyId)
            ->findOrFail($request->input('assigned_technician'));

        $oldAssignedTechnician = $job->assigned_technician;
        $job->assigned_technician = $technician->id;
        $job->save();

        // Create activity log
        JobActivity::create([
            'job_id' => $job->id,
            'user_id' => auth()->id(),
            'type' => 'assigned',
            'description' => "Assigned to {$technician->first_name} {$technician->last_name}",
            'metadata' => [
                'old_assigned_technician' => $oldAssignedTechnician,
                'new_assigned_technician' => $technician->id,
            ],
        ]);

        return $this->success(
            $job->fresh()->load('client', 'technician', 'quote')->toArray(),
            'Job assigned successfully'
        );
    }

    public function complete(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $job = Job::where('company_id', $companyId)->findOrFail($id);

        // Only in_progress jobs can be completed
        if ($job->status !== 'in_progress') {
            return $this->error('Only in-progress jobs can be completed', null, 422);
        }

        $validator = Validator::make($request->all(), [
            'actual_duration' => 'nullable|numeric|min:0',
            'signature' => 'nullable|string',
            'notes' => 'nullable|string',
            'photos' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            $job->status = 'completed';
            $job->completed_date = now();
            
            if ($request->has('actual_duration')) {
                $job->actual_duration = $request->input('actual_duration');
            }
            
            if ($request->has('signature')) {
                $job->signature = $request->input('signature');
            }
            
            if ($request->has('notes')) {
                $job->notes = $request->input('notes');
            }
            
            if ($request->has('photos')) {
                $job->photos = $request->input('photos');
            }

            // Calculate costs
            $materialCost = $job->calculateMaterialCost();
            $laborCost = $job->calculateLaborCost(); // Default £50/hour
            $actualCost = $materialCost + $laborCost;
            
            $job->material_cost = $materialCost;
            $job->labor_cost = $laborCost;
            $job->actual_cost = $actualCost;
            
            // Calculate profit margin if quote exists
            if ($job->quote) {
                $quoteTotal = $job->quote->total ?? 0;
                if ($quoteTotal > 0) {
                    $profit = $quoteTotal - $actualCost;
                    $job->profit_margin = ($profit / $quoteTotal) * 100;
                }
            }

            $job->save();

            // Create activity log
            JobActivity::create([
                'job_id' => $job->id,
                'user_id' => auth()->id(),
                'type' => 'completed',
                'description' => 'Job completed',
                'metadata' => [
                    'actual_duration' => $job->actual_duration,
                    'completed_date' => $job->completed_date,
                    'actual_cost' => $actualCost,
                    'material_cost' => $materialCost,
                    'labor_cost' => $laborCost,
                ],
            ]);

            DB::commit();

            return $this->success(
                $job->fresh()->load('client', 'technician', 'quote')->toArray(),
                'Job completed successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to complete job: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get activities for a job
     */
    public function activities($id)
    {
        $companyId = $this->getCompanyId();
        $job = Job::where('company_id', $companyId)->findOrFail($id);

        $activities = JobActivity::where('job_id', $job->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($activities->toArray());
    }
}

