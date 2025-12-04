<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Company;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Models\LeadActivity;
use Carbon\Carbon;

class LeadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::where('email', 'demo@workero.com')->first();
        
        if (!$company) {
            $this->command->error('Demo company not found. Please run DatabaseSeeder first.');
            return;
        }

        $clients = Client::where('company_id', $company->id)->get();
        
        if ($clients->isEmpty()) {
            $this->command->error('No clients found. Please run DatabaseSeeder first.');
            return;
        }

        $users = User::where('company_id', $company->id)->get();
        $admin = $users->where('role', 'admin')->first();
        $dispatcher = $users->where('role', 'dispatcher')->first();

        // Sample lead data - creating 10 leads like in the local version
        // Note: source must match ENUM values: 'website', 'referral', 'advertisement', 'cold_call', 'other'
        $leadData = [
            [
                'client' => $clients->first(),
                'source' => 'website',
                'status' => 'contacted',
                'priority' => 'low',
                'estimated_value' => 1500.00,
                'assigned_to' => $admin?->id,
                'created_days_ago' => 8,
            ],
            [
                'client' => $clients->first(),
                'source' => 'cold_call',
                'status' => 'new',
                'priority' => 'medium',
                'estimated_value' => 1197.00,
                'assigned_to' => $dispatcher?->id,
                'created_days_ago' => 8,
            ],
            [
                'client' => $clients->first(),
                'source' => 'referral',
                'status' => 'qualified',
                'priority' => 'high',
                'estimated_value' => 2500.00,
                'assigned_to' => $admin?->id,
                'created_days_ago' => 7,
            ],
            [
                'client' => $clients->first(),
                'source' => 'other', // Email maps to 'other'
                'status' => 'contacted',
                'priority' => 'medium',
                'estimated_value' => 1800.00,
                'assigned_to' => $dispatcher?->id,
                'created_days_ago' => 6,
            ],
            [
                'client' => $clients->first(),
                'source' => 'advertisement',
                'status' => 'new',
                'priority' => 'urgent',
                'estimated_value' => 4755.00,
                'assigned_to' => $dispatcher?->id,
                'created_days_ago' => 8,
            ],
            [
                'client' => $clients->first(),
                'source' => 'advertisement',
                'status' => 'new',
                'priority' => 'high',
                'estimated_value' => 3869.00,
                'assigned_to' => $admin?->id,
                'created_days_ago' => 8,
            ],
            [
                'client' => $clients->first(),
                'source' => 'other', // Social Media maps to 'other'
                'status' => 'contacted',
                'priority' => 'low',
                'estimated_value' => 950.00,
                'assigned_to' => $dispatcher?->id,
                'created_days_ago' => 5,
            ],
            [
                'client' => $clients->first(),
                'source' => 'website',
                'status' => 'quoted',
                'priority' => 'medium',
                'estimated_value' => 2200.00,
                'assigned_to' => $admin?->id,
                'created_days_ago' => 4,
            ],
            [
                'client' => $clients->first(),
                'source' => 'referral',
                'status' => 'qualified',
                'priority' => 'high',
                'estimated_value' => 3200.00,
                'assigned_to' => $admin?->id,
                'created_days_ago' => 3,
            ],
            [
                'client' => $clients->first(),
                'source' => 'cold_call',
                'status' => 'new',
                'priority' => 'medium',
                'estimated_value' => 1500.00,
                'assigned_to' => $dispatcher?->id,
                'created_days_ago' => 2,
            ],
        ];

        $createdCount = 0;

        foreach ($leadData as $data) {
            $createdAt = Carbon::now()->subDays($data['created_days_ago']);
            
            $lead = Lead::create([
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'client_id' => $data['client']->id,
                'source' => $data['source'],
                'status' => $data['status'],
                'priority' => $data['priority'],
                'estimated_value' => $data['estimated_value'],
                'assigned_to' => $data['assigned_to'],
                'notes' => "Demo lead created from " . ucfirst(str_replace('_', ' ', $data['source'])),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Create activity log for lead creation
            LeadActivity::create([
                'id' => Str::uuid(),
                'lead_id' => $lead->id,
                'user_id' => $data['assigned_to'],
                'type' => 'created',
                'description' => "Lead created from " . ucfirst(str_replace('_', ' ', $data['source'])),
                'metadata' => [
                    'source' => ucfirst(str_replace('_', ' ', $data['source'])),
                    'status' => $data['status'],
                    'priority' => $data['priority'],
                ],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $createdCount++;
        }

        $this->command->info("Created {$createdCount} demo leads successfully!");
    }
}

