<?php

namespace Database\Seeders;

use App\Models\TechnicianAvailability;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TechnicianAvailabilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::where('role', 'technician')
            ->whereNotNull('company_id')
            ->chunkById(50, function ($technicians) {
                foreach ($technicians as $technician) {
                    $this->seedDefaultsForTechnician($technician->company_id, $technician->id);
                }
            });

        // Ensure each company has a baseline (company-wide) availability template
        $companyIds = User::where('role', 'technician')
            ->whereNotNull('company_id')
            ->pluck('company_id')
            ->unique();

        foreach ($companyIds as $companyId) {
            $this->seedDefaultsForTechnician($companyId, null);
        }
    }

    protected function seedDefaultsForTechnician(string $companyId, ?string $technicianId): void
    {
        foreach (range(0, 6) as $dayOfWeek) {
            TechnicianAvailability::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'technician_id' => $technicianId,
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'is_available' => !in_array($dayOfWeek, [0, 6], true),
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'timezone' => config('app.timezone', 'UTC'),
                    'max_hours_per_day' => 8,
                    'max_jobs_per_day' => 6,
                ]
            );
        }
    }
}


