<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;

class InventorySeeder extends Seeder
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

        // Create warehouses
        $warehouse1 = Warehouse::firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'WH-001',
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Main Warehouse',
                'address' => '123 Warehouse Street',
                'city' => 'London',
                'state' => 'England',
                'zip_code' => 'SW1A 1AA',
                'country' => 'United Kingdom',
                'contact_person' => 'John Warehouse',
                'phone' => '+44 123 456 7890',
                'email' => 'warehouse@workero.com',
                'is_active' => true,
            ]
        );

        $warehouse2 = Warehouse::firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'WH-002',
            ],
            [
                'id' => Str::uuid(),
                'name' => 'North Warehouse',
                'address' => '456 North Road',
                'city' => 'Manchester',
                'state' => 'England',
                'zip_code' => 'M1 1AA',
                'country' => 'United Kingdom',
                'contact_person' => 'Jane Manager',
                'phone' => '+44 987 654 3210',
                'email' => 'north@workero.com',
                'is_active' => true,
            ]
        );

        // Create suppliers
        $supplier1 = Supplier::firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Plumbing Supplies Co',
            ],
            [
                'id' => Str::uuid(),
                'contact_person' => 'Mike Supplier',
                'email' => 'sales@plumbingsupplies.co.uk',
                'phone' => '+44 111 222 3333',
                'address' => '789 Supplier Lane',
                'city' => 'Birmingham',
                'state' => 'England',
                'zip_code' => 'B1 1AA',
                'country' => 'United Kingdom',
                'website' => 'https://plumbingsupplies.co.uk',
                'is_active' => true,
            ]
        );

        $supplier2 = Supplier::firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Electrical Components Ltd',
            ],
            [
                'id' => Str::uuid(),
                'contact_person' => 'Sarah Components',
                'email' => 'info@electricalcomponents.co.uk',
                'phone' => '+44 444 555 6666',
                'address' => '321 Component Street',
                'city' => 'Leeds',
                'state' => 'England',
                'zip_code' => 'LS1 1AA',
                'country' => 'United Kingdom',
                'website' => 'https://electricalcomponents.co.uk',
                'is_active' => true,
            ]
        );

        $supplier3 = Supplier::firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Heating Solutions UK',
            ],
            [
                'id' => Str::uuid(),
                'contact_person' => 'Tom Heating',
                'email' => 'contact@heatingsolutions.co.uk',
                'phone' => '+44 777 888 9999',
                'address' => '654 Heating Avenue',
                'city' => 'Bristol',
                'state' => 'England',
                'zip_code' => 'BS1 1AA',
                'country' => 'United Kingdom',
                'website' => 'https://heatingsolutions.co.uk',
                'is_active' => true,
            ]
        );

        // Create inventory items
        $inventoryData = [
            // Plumbing items
            [
                'name' => 'Copper Pipe 15mm',
                'description' => '15mm diameter copper pipe for plumbing installations',
                'sku' => 'PIPE-15MM-CU',
                'barcode' => '1234567890123',
                'category' => 'Plumbing',
                'quantity' => 500.00,
                'current_stock' => 500.00,
                'min_stock' => 100.00,
                'max_stock' => 1000.00,
                'unit_price' => 8.50,
                'cost_price' => 5.00,
                'reorder_point' => 150.00,
                'location' => 'Aisle 1, Shelf B',
                'warehouse_id' => $warehouse1->id,
            ],
            [
                'name' => 'Copper Pipe 22mm',
                'description' => '22mm diameter copper pipe for main water lines',
                'sku' => 'PIPE-22MM-CU',
                'barcode' => '1234567890124',
                'category' => 'Plumbing',
                'quantity' => 300.00,
                'current_stock' => 300.00,
                'min_stock' => 50.00,
                'max_stock' => 500.00,
                'unit_price' => 12.00,
                'cost_price' => 7.50,
                'reorder_point' => 75.00,
                'location' => 'Aisle 1, Shelf C',
                'warehouse_id' => $warehouse1->id,
            ],
            [
                'name' => 'Pipe Fittings Set',
                'description' => 'Assorted pipe fittings including elbows, tees, and couplings',
                'sku' => 'FITTINGS-SET-001',
                'barcode' => '1234567890125',
                'category' => 'Plumbing',
                'quantity' => 250.00,
                'current_stock' => 250.00,
                'min_stock' => 50.00,
                'max_stock' => 400.00,
                'unit_price' => 15.00,
                'cost_price' => 9.00,
                'reorder_point' => 75.00,
                'location' => 'Aisle 1, Shelf D',
                'warehouse_id' => $warehouse1->id,
            ],
            [
                'name' => 'Pipe Sealant',
                'description' => 'Thread sealant for pipe connections',
                'sku' => 'SEALANT-001',
                'barcode' => '1234567890126',
                'category' => 'Plumbing',
                'quantity' => 100.00,
                'current_stock' => 100.00,
                'min_stock' => 20.00,
                'max_stock' => 200.00,
                'unit_price' => 8.50,
                'cost_price' => 4.50,
                'reorder_point' => 30.00,
                'location' => 'Aisle 2, Shelf A',
                'warehouse_id' => $warehouse1->id,
            ],
            // Electrical items
            [
                'name' => 'Electrical Wire 2.5mm',
                'description' => '2.5mm² twin and earth electrical wire',
                'sku' => 'WIRE-2.5MM',
                'barcode' => '1234567890127',
                'category' => 'Electrical',
                'quantity' => 1000.00,
                'current_stock' => 1000.00,
                'min_stock' => 200.00,
                'max_stock' => 2000.00,
                'unit_price' => 2.50,
                'cost_price' => 1.50,
                'reorder_point' => 300.00,
                'location' => 'Aisle 3, Shelf A',
                'warehouse_id' => $warehouse1->id,
            ],
            [
                'name' => 'Circuit Breaker 20A',
                'description' => '20 Amp single pole circuit breaker',
                'sku' => 'CB-20A-SP',
                'barcode' => '1234567890128',
                'category' => 'Electrical',
                'quantity' => 150.00,
                'current_stock' => 150.00,
                'min_stock' => 30.00,
                'max_stock' => 300.00,
                'unit_price' => 25.00,
                'cost_price' => 15.00,
                'reorder_point' => 50.00,
                'location' => 'Aisle 3, Shelf B',
                'warehouse_id' => $warehouse1->id,
            ],
            [
                'name' => 'Electrical Outlet',
                'description' => 'Standard UK electrical outlet socket',
                'sku' => 'OUTLET-UK-STD',
                'barcode' => '1234567890129',
                'category' => 'Electrical',
                'quantity' => 200.00,
                'current_stock' => 200.00,
                'min_stock' => 40.00,
                'max_stock' => 400.00,
                'unit_price' => 12.00,
                'cost_price' => 7.00,
                'reorder_point' => 60.00,
                'location' => 'Aisle 3, Shelf C',
                'warehouse_id' => $warehouse1->id,
            ],
            // Heating items
            [
                'name' => 'Radiator Valve',
                'description' => 'Thermostatic radiator valve',
                'sku' => 'RAD-VALVE-TRV',
                'barcode' => '1234567890130',
                'category' => 'Heating',
                'quantity' => 80.00,
                'current_stock' => 80.00,
                'min_stock' => 15.00,
                'max_stock' => 150.00,
                'unit_price' => 35.00,
                'cost_price' => 20.00,
                'reorder_point' => 25.00,
                'location' => 'Aisle 4, Shelf A',
                'warehouse_id' => $warehouse1->id,
            ],
            [
                'name' => 'Boiler Service Kit',
                'description' => 'Complete boiler service and maintenance kit',
                'sku' => 'BOILER-SVC-KIT',
                'barcode' => '1234567890131',
                'category' => 'Heating',
                'quantity' => 50.00,
                'current_stock' => 50.00,
                'min_stock' => 10.00,
                'max_stock' => 100.00,
                'unit_price' => 45.00,
                'cost_price' => 25.00,
                'reorder_point' => 20.00,
                'location' => 'Aisle 4, Shelf B',
                'warehouse_id' => $warehouse1->id,
            ],
            [
                'name' => 'Thermostat',
                'description' => 'Smart programmable thermostat',
                'sku' => 'THERMOSTAT-SMART',
                'barcode' => '1234567890132',
                'category' => 'Heating',
                'quantity' => 30.00,
                'current_stock' => 30.00,
                'min_stock' => 5.00,
                'max_stock' => 60.00,
                'unit_price' => 120.00,
                'cost_price' => 75.00,
                'reorder_point' => 10.00,
                'location' => 'Aisle 4, Shelf C',
                'warehouse_id' => $warehouse1->id,
            ],
            // Low stock items (for alerts)
            [
                'name' => 'Pipe Repair Kit',
                'description' => 'Emergency pipe repair kit',
                'sku' => 'PIPE-REPAIR-KIT',
                'barcode' => '1234567890133',
                'category' => 'Plumbing',
                'quantity' => 8.00,
                'current_stock' => 8.00,
                'min_stock' => 20.00,
                'max_stock' => 100.00,
                'unit_price' => 30.00,
                'cost_price' => 18.00,
                'reorder_point' => 25.00,
                'location' => 'Aisle 2, Shelf B',
                'warehouse_id' => $warehouse1->id,
            ],
            [
                'name' => 'Pump Motor',
                'description' => 'Replacement pump motor for dishwashers',
                'sku' => 'PUMP-MOTOR-DW',
                'barcode' => '1234567890134',
                'category' => 'Appliances',
                'quantity' => 5.00,
                'current_stock' => 5.00,
                'min_stock' => 10.00,
                'max_stock' => 50.00,
                'unit_price' => 120.00,
                'cost_price' => 70.00,
                'reorder_point' => 15.00,
                'location' => 'Aisle 5, Shelf A',
                'warehouse_id' => $warehouse1->id,
            ],
        ];

        $createdCount = 0;
        $createdItems = [];

        foreach ($inventoryData as $data) {
            $item = InventoryItem::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'sku' => $data['sku'],
                ],
                array_merge($data, [
                    'id' => Str::uuid(),
                    'company_id' => $company->id,
                    'last_audit_date' => Carbon::now()->subMonths(1),
                ])
            );
            $createdItems[] = $item;
            $createdCount++;
        }

        // Get admin user for performed_by
        $adminUser = User::where('company_id', $company->id)
            ->where('email', 'admin@workero.com')
            ->first();

        if (!$adminUser) {
            // Fallback to any user in the company
            $adminUser = User::where('company_id', $company->id)->first();
        }

        // Create sample stock movements
        if ($adminUser && count($createdItems) > 0) {
            $movementReasons = [
                'in' => ['Stock received from supplier', 'Initial stock entry', 'Return from job', 'Stock adjustment'],
                'out' => ['Issued to job', 'Stock adjustment', 'Damaged items removed', 'Returned to supplier'],
                'transfer' => ['Transfer between warehouses', 'Stock relocation', 'Warehouse reorganization'],
            ];

            // Create movements for the last 7 days
            $movementCount = 0;
            foreach ($createdItems as $index => $item) {
                // Create 1-3 movements per item
                $numMovements = rand(1, 3);
                
                for ($i = 0; $i < $numMovements; $i++) {
                    $type = ['in', 'out', 'transfer'][rand(0, 2)];
                    $daysAgo = rand(0, 7);
                    $performedAt = Carbon::now()->subDays($daysAgo)->subHours(rand(0, 23));
                    
                    $quantity = match($type) {
                        'in' => rand(10, 100),
                        'out' => rand(5, min(50, $item->current_stock)),
                        'transfer' => rand(5, min(30, $item->current_stock)),
                    };
                    
                    $reason = $movementReasons[$type][array_rand($movementReasons[$type])];
                    
                    $fromLocation = null;
                    $toLocation = null;
                    
                    if ($type === 'in') {
                        $toLocation = $item->location ?? 'Main Warehouse';
                    } elseif ($type === 'out') {
                        $fromLocation = $item->location ?? 'Main Warehouse';
                        $toLocation = 'Job-' . Str::random(8);
                    } else { // transfer
                        $fromLocation = $warehouse1->name;
                        $toLocation = $warehouse2->name;
                    }
                    
                    StockMovement::create([
                        'id' => Str::uuid(),
                        'company_id' => $company->id,
                        'item_id' => $item->id,
                        'type' => $type,
                        'quantity' => $quantity,
                        'from_location' => $fromLocation,
                        'to_location' => $toLocation,
                        'reason' => $reason,
                        'performed_by' => $adminUser->id,
                        'performed_at' => $performedAt,
                        'notes' => rand(0, 1) ? 'Demo stock movement for testing' : null,
                    ]);
                    
                    $movementCount++;
                }
            }
            
            $this->command->info("Created {$movementCount} stock movements successfully!");
        }

        $this->command->info("Created {$createdCount} inventory items successfully!");
        $this->command->info("Created 2 warehouses successfully!");
        $this->command->info("Created 3 suppliers successfully!");
    }
}

