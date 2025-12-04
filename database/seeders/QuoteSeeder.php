<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Company;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use Carbon\Carbon;

class QuoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the demo company
        $company = Company::where('email', 'demo@workero.com')->first();
        
        if (!$company) {
            $this->command->error('Demo company not found. Please run DatabaseSeeder first.');
            return;
        }

        // Get all clients for this company
        $clients = Client::where('company_id', $company->id)->get();
        
        if ($clients->isEmpty()) {
            $this->command->error('No clients found. Please run DatabaseSeeder first.');
            return;
        }

        // Sample quote data
        $quoteData = [
            [
                'status' => 'draft',
                'items' => [
                    ['description' => 'Plumbing Repair Service', 'quantity' => 2, 'unit_price' => 150.00, 'tax_rate' => 20.0],
                    ['description' => 'Pipe Replacement', 'quantity' => 1, 'unit_price' => 250.00, 'tax_rate' => 20.0],
                ],
                'notes' => 'Standard plumbing repair and pipe replacement service.',
            ],
            [
                'status' => 'sent',
                'items' => [
                    ['description' => 'Heating System Installation', 'quantity' => 1, 'unit_price' => 2500.00, 'tax_rate' => 20.0],
                    ['description' => 'Boiler Service', 'quantity' => 1, 'unit_price' => 120.00, 'tax_rate' => 20.0],
                ],
                'notes' => 'Complete heating system installation with annual service included.',
            ],
            [
                'status' => 'sent',
                'items' => [
                    ['description' => 'Bathroom Renovation', 'quantity' => 1, 'unit_price' => 5000.00, 'tax_rate' => 20.0],
                    ['description' => 'Tiles and Fixtures', 'quantity' => 1, 'unit_price' => 1500.00, 'tax_rate' => 20.0],
                ],
                'notes' => 'Full bathroom renovation including tiles and all fixtures.',
            ],
            [
                'status' => 'accepted',
                'items' => [
                    ['description' => 'Kitchen Plumbing', 'quantity' => 1, 'unit_price' => 800.00, 'tax_rate' => 20.0],
                    ['description' => 'Dishwasher Installation', 'quantity' => 1, 'unit_price' => 150.00, 'tax_rate' => 20.0],
                ],
                'notes' => 'Kitchen plumbing work and dishwasher installation.',
            ],
            [
                'status' => 'accepted',
                'items' => [
                    ['description' => 'Emergency Callout', 'quantity' => 1, 'unit_price' => 200.00, 'tax_rate' => 20.0],
                    ['description' => 'Leak Repair', 'quantity' => 1, 'unit_price' => 180.00, 'tax_rate' => 20.0],
                ],
                'notes' => 'Emergency callout and leak repair service.',
            ],
            [
                'status' => 'rejected',
                'items' => [
                    ['description' => 'Full House Rewiring', 'quantity' => 1, 'unit_price' => 8000.00, 'tax_rate' => 20.0],
                ],
                'notes' => 'Complete house rewiring project.',
            ],
            [
                'status' => 'draft',
                'items' => [
                    ['description' => 'Radiator Installation', 'quantity' => 5, 'unit_price' => 200.00, 'tax_rate' => 20.0],
                    ['description' => 'Thermostat Upgrade', 'quantity' => 1, 'unit_price' => 300.00, 'tax_rate' => 20.0],
                ],
                'notes' => 'Installation of 5 new radiators and smart thermostat upgrade.',
            ],
        ];

        $createdCount = 0;

        foreach ($quoteData as $index => $data) {
            // Cycle through clients
            $client = $clients[$index % $clients->count()];
            
            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;
            
            foreach ($data['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineTax = $lineTotal * ($item['tax_rate'] / 100);
                $subtotal += $lineTotal;
                $taxAmount += $lineTax;
            }
            
            $total = $subtotal + $taxAmount;
            $profitMargin = 25.0; // 25% profit margin
            
            // Create quote
            $quote = Quote::create([
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'client_id' => $client->id,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'profit_margin' => $profitMargin,
                'status' => $data['status'],
                'valid_until' => Carbon::now()->addDays(30),
                'notes' => $data['notes'],
            ]);
            
            // Create quote items
            foreach ($data['items'] as $itemData) {
                $lineTotal = $itemData['quantity'] * $itemData['unit_price'];
                
                QuoteItem::create([
                    'id' => Str::uuid(),
                    'quote_id' => $quote->id,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_rate' => $itemData['tax_rate'],
                    'line_total' => $lineTotal,
                ]);
            }
            
            $createdCount++;
        }

        $this->command->info("Created {$createdCount} quotes with items successfully!");
    }
}

