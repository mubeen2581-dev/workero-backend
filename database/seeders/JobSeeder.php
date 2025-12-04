<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Client;
use App\Models\Job;
use App\Models\Quote;
use App\Models\User;
use App\Models\JobActivity;
use Illuminate\Support\Str;
use Carbon\Carbon;

class JobSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();
        $client = Client::first();
        $technician = User::where('role', 'technician')->first();

        if (!$company || !$client) {
            $this->command->warn('Company or Client not found. Please run DatabaseSeeder first.');
            return;
        }

        // Get accepted quotes to convert some to jobs
        $acceptedQuotes = Quote::where('company_id', $company->id)
            ->where('status', 'accepted')
            ->take(2)
            ->get();

        // Create scheduled job from accepted quote
        if ($acceptedQuotes->count() > 0) {
            $quote = $acceptedQuotes->first();
            $this->createJob(
                $company,
                $client,
                $technician,
                'scheduled',
                'high',
                'HVAC System Installation',
                'Install and commission new HVAC system as per accepted quote. Includes ductwork modifications and safety testing.',
                8.0,
                Carbon::now()->addDays(2)->setTime(9, 0),
                [
                    'address' => $client->address['street'] . ', ' . $client->address['city'],
                    'coordinates' => ['lat' => 51.5074, 'lng' => -0.1278]
                ],
                [
                    ['id' => Str::uuid(), 'name' => 'HVAC Unit', 'quantity' => 1, 'unit_price' => 2500.00],
                    ['id' => Str::uuid(), 'name' => 'Ductwork Kit', 'quantity' => 1, 'unit_price' => 400.00],
                ],
                'Job created from accepted quote. All materials ordered and ready for installation.',
                $quote->id
            );
        }

        // Create in-progress job
        $this->createJob(
            $company,
            $client,
            $technician,
            'in_progress',
            'urgent',
            'Emergency Plumbing Repair',
            'Urgent plumbing repair - burst pipe in kitchen. Immediate attention required to prevent water damage.',
            2.5,
            Carbon::now()->setTime(10, 0),
            [
                'address' => $client->address['street'] . ', ' . $client->address['city'],
                'coordinates' => ['lat' => 51.5074, 'lng' => -0.1278]
            ],
            [
                ['id' => Str::uuid(), 'name' => 'Pipe Repair Kit', 'quantity' => 1, 'unit_price' => 30.00],
                ['id' => Str::uuid(), 'name' => 'Sealant', 'quantity' => 2, 'unit_price' => 8.50],
            ],
            'Emergency call-out. Customer reported burst pipe at 9:30 AM. Technician dispatched immediately.',
            null,
            1.5 // actual duration
        );

        // Create completed job
        $completedJob = $this->createJob(
            $company,
            $client,
            $technician,
            'completed',
            'medium',
            'Electrical Wiring Upgrade',
            'Complete electrical rewiring for 3 rooms. New circuit breaker panel installed. Safety inspection passed.',
            6.0,
            Carbon::now()->subDays(3)->setTime(8, 0),
            [
                'address' => $client->address['street'] . ', ' . $client->address['city'],
                'coordinates' => ['lat' => 51.5074, 'lng' => -0.1278]
            ],
            [
                ['id' => Str::uuid(), 'name' => 'Circuit Breaker Panel', 'quantity' => 1, 'unit_price' => 600.00],
                ['id' => Str::uuid(), 'name' => 'Electrical Wire (per room)', 'quantity' => 3, 'unit_price' => 200.00],
                ['id' => Str::uuid(), 'name' => 'Safety Inspection', 'quantity' => 1, 'unit_price' => 100.00],
            ],
            'Job completed successfully. All safety checks passed. Customer satisfied with work.',
            null,
            5.5, // actual duration
            Carbon::now()->subDays(3)->setTime(16, 30) // completed date
        );

        // Calculate costs for completed job
        $materialCost = 600 + (3 * 200) + 100; // 1300
        $laborCost = 5.5 * 50; // 275
        $actualCost = $materialCost + $laborCost; // 1575
        $completedJob->material_cost = $materialCost;
        $completedJob->labor_cost = $laborCost;
        $completedJob->actual_cost = $actualCost;
        $completedJob->save();

        // Create scheduled job (low priority)
        $this->createJob(
            $company,
            $client,
            null, // unassigned
            'scheduled',
            'low',
            'Bathroom Renovation Planning',
            'Initial consultation and planning for bathroom renovation. Measure space, discuss options, and prepare detailed quote.',
            2.0,
            Carbon::now()->addDays(7)->setTime(14, 0),
            [
                'address' => $client->address['street'] . ', ' . $client->address['city'],
                'coordinates' => ['lat' => 51.5074, 'lng' => -0.1278]
            ],
            null,
            'Initial consultation visit. No materials required at this stage.',
            null
        );

        // Create another scheduled job
        $this->createJob(
            $company,
            $client,
            $technician,
            'scheduled',
            'medium',
            'Kitchen Appliance Repair',
            'Repair dishwasher - diagnostic and replace pump motor if needed.',
            1.5,
            Carbon::now()->addDays(5)->setTime(11, 0),
            [
                'address' => $client->address['street'] . ', ' . $client->address['city'],
                'coordinates' => ['lat' => 51.5074, 'lng' => -0.1278]
            ],
            [
                ['id' => Str::uuid(), 'name' => 'Pump Motor', 'quantity' => 1, 'unit_price' => 120.00],
            ],
            'Customer reported dishwasher not draining. Scheduled for diagnostic and repair.',
            null
        );

        $this->command->info('Test jobs seeded successfully!');
    }

    private function createJob(
        Company $company,
        Client $client,
        ?User $technician,
        string $status,
        string $priority,
        string $title,
        string $description,
        float $estimatedDuration,
        Carbon $scheduledDate,
        array $location,
        ?array $materials,
        string $notes,
        ?string $quoteId = null,
        ?float $actualDuration = null,
        ?Carbon $completedDate = null
    ): Job {
        $job = Job::create([
            'id' => Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'quote_id' => $quoteId,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'priority' => $priority,
            'estimated_duration' => $estimatedDuration,
            'actual_duration' => $actualDuration,
            'assigned_technician' => $technician?->id,
            'scheduled_date' => $scheduledDate,
            'completed_date' => $completedDate,
            'location' => $location,
            'materials' => $materials,
            'notes' => $notes,
        ]);

        // Create activity log for job creation
        JobActivity::create([
            'job_id' => $job->id,
            'user_id' => null, // System created
            'type' => 'created',
            'description' => 'Job created',
            'metadata' => [
                'status' => $status,
                'priority' => $priority,
                'title' => $title,
            ],
        ]);

        // Create assignment activity if technician assigned
        if ($technician) {
            JobActivity::create([
                'job_id' => $job->id,
                'user_id' => null,
                'type' => 'assigned',
                'description' => "Assigned to {$technician->first_name} {$technician->last_name}",
                'metadata' => [
                    'assigned_technician' => $technician->id,
                ],
            ]);
        }

        // Create completion activity if job is completed
        if ($status === 'completed' && $completedDate) {
            JobActivity::create([
                'job_id' => $job->id,
                'user_id' => $technician?->id,
                'type' => 'completed',
                'description' => 'Job completed',
                'metadata' => [
                    'actual_duration' => $actualDuration,
                    'completed_date' => $completedDate->toDateTimeString(),
                ],
            ]);
        }

        return $job;
    }
}

