<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Company;
use App\Models\User;
use App\Models\Client;
use Database\Seeders\QuoteSeeder;
use Database\Seeders\JobSeeder;
use Database\Seeders\LeadSeeder;
use Database\Seeders\InventorySeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Get or create a demo company
        $company = Company::firstOrCreate(
            ['email' => 'demo@workero.com'],
            [
                'id' => Str::uuid(),
                'name' => 'Demo Company',
                'phone' => '+44 123 456 7890',
                'address' => [
                    'street' => '123 Business Street',
                    'city' => 'London',
                    'state' => 'England',
                    'zipCode' => 'SW1A 1AA',
                    'country' => 'United Kingdom',
                ],
                'subscription_tier' => 'pro',
                'is_active' => true,
            ]
        );

        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@workero.com'],
            [
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'password' => bcrypt('password123'),
                'first_name' => 'Admin',
                'last_name' => 'User',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // Create manager user
        User::firstOrCreate(
            ['email' => 'manager@workero.com'],
            [
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'password' => bcrypt('password123'),
                'first_name' => 'Manager',
                'last_name' => 'User',
                'role' => 'manager',
                'is_active' => true,
            ]
        );

        // Create technician user
        User::firstOrCreate(
            ['email' => 'tech@workero.com'],
            [
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'password' => bcrypt('password123'),
                'first_name' => 'Tech',
                'last_name' => 'User',
                'role' => 'technician',
                'team' => 'Team A',
                'region' => 'North',
                'skills' => ['plumbing', 'heating'],
                'is_active' => true,
            ]
        );

        // Create dispatcher user (Office Admin)
        User::firstOrCreate(
            ['email' => 'dispatcher@workero.com'],
            [
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'password' => bcrypt('password123'),
                'first_name' => 'Office',
                'last_name' => 'Admin',
                'role' => 'dispatcher',
                'is_active' => true,
            ]
        );

        // Create warehouse user (Stock Manager)
        User::firstOrCreate(
            ['email' => 'warehouse@workero.com'],
            [
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'password' => bcrypt('password123'),
                'first_name' => 'Warehouse',
                'last_name' => 'Manager',
                'role' => 'warehouse',
                'is_active' => true,
            ]
        );

        // Create client user (View-only access)
        User::firstOrCreate(
            ['email' => 'client@workero.com'],
            [
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'password' => bcrypt('password123'),
                'first_name' => 'Client',
                'last_name' => 'User',
                'role' => 'client',
                'is_active' => true,
            ]
        );

        // Create demo client
        Client::firstOrCreate(
            ['email' => 'client@example.com'],
            [
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'name' => 'Demo Client Ltd',
                'phone' => '+44 987 654 3210',
                'address' => [
                    'street' => '456 Client Road',
                    'city' => 'Manchester',
                    'state' => 'England',
                    'zipCode' => 'M1 1AA',
                    'country' => 'United Kingdom',
                ],
                'tags' => ['premium', 'recurring'],
                'lead_score' => 85,
            ]
        );

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Company ID: ' . $company->id);
        $this->command->info('');
        $this->command->info('Test User Credentials (all use password: password123):');
        $this->command->info('  Admin:      admin@workero.com');
        $this->command->info('  Manager:    manager@workero.com');
        $this->command->info('  Technician: tech@workero.com');
        $this->command->info('  Dispatcher: dispatcher@workero.com');
        $this->command->info('  Warehouse:  warehouse@workero.com');
        $this->command->info('  Client:     client@workero.com');
        
        // Seed leads
        $this->call(LeadSeeder::class);
        
        // Seed quotes
        $this->call(QuoteSeeder::class);
        
        // Seed jobs
        $this->call(JobSeeder::class);
        
        // Seed inventory (warehouses, items, suppliers)
        $this->call(InventorySeeder::class);

        // Seed technician availability defaults
        $this->call(TechnicianAvailabilitySeeder::class);
        
        // Seed communication (conversations and messages)
        $this->call(CommunicationSeeder::class);
    }
}

