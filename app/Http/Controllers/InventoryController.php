<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\VanStock;
use App\Models\StockAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    /**
     * Get all inventory items with filtering, searching, and pagination
     */
    public function items(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = InventoryItem::where('company_id', $companyId);
        
        // Search by name, SKU, or barcode
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }
        
        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }
        
        // Filter by location
        if ($request->has('location')) {
            $query->where('location', $request->input('location'));
        }
        
        // Filter by low stock
        if ($request->has('low_stock') && $request->input('low_stock') === 'true') {
            $query->whereColumn('current_stock', '<=', 'reorder_point');
        }
        
        // Sort
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);
        
        $perPage = $request->input('per_page', 15);
        $items = $query->paginate($perPage);
        
        return $this->paginated($items->items(), [
            'page' => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
            'totalPages' => $items->lastPage(),
        ]);
    }

    /**
     * Get a single inventory item by ID
     */
    public function show($id)
    {
        $companyId = $this->getCompanyId();
        $item = InventoryItem::where('company_id', $companyId)
            ->with('stockMovements')
            ->findOrFail($id);
        
        return $this->success($item);
    }

    /**
     * Create a new inventory item
     */
    public function store(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'required|string|max:255|unique:inventory_items,sku',
            'barcode' => 'nullable|string|max:255',
            'category' => 'required|string|max:255',
            'quantity' => 'nullable|numeric|min:0',
            'current_stock' => 'nullable|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'max_quantity' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'warehouse_id' => 'nullable|uuid|exists:warehouses,id',
        ]);
        
        $validated['company_id'] = $companyId;
        
        // Set default location if not provided
        if (empty($validated['location'])) {
            $validated['location'] = 'warehouse';
        }
        
        // Validate warehouse belongs to company if provided
        if (isset($validated['warehouse_id'])) {
            $warehouse = \App\Models\Warehouse::where('company_id', $companyId)
                ->find($validated['warehouse_id']);
            if (!$warehouse) {
                return $this->error('Warehouse not found or does not belong to your company', 422);
            }
        }
        
        // Set current_stock to quantity if not provided
        if (!isset($validated['current_stock']) && isset($validated['quantity'])) {
            $validated['current_stock'] = $validated['quantity'];
        }
        
        // Generate barcode if not provided and validate uniqueness
        if (empty($validated['barcode'])) {
            // Generate unique barcode
            do {
                $barcode = 'BC-' . strtoupper(Str::random(10));
            } while (InventoryItem::where('barcode', $barcode)->where('company_id', $companyId)->exists());
            $validated['barcode'] = $barcode;
        } else {
            // Validate barcode uniqueness if provided
            $exists = InventoryItem::where('barcode', $validated['barcode'])
                ->where('company_id', $companyId)
                ->exists();
            if ($exists) {
                return $this->error('Barcode already exists', 422);
            }
        }
        
        try {
            $item = InventoryItem::create($validated);
            return $this->success($item, 'Inventory item created successfully', 201);
        } catch (\Exception $e) {
            \Log::error('Inventory item creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'validated' => $validated
            ]);
            return $this->error('Failed to create inventory item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing inventory item
     */
    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $item = InventoryItem::where('company_id', $companyId)->findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'sku' => ['sometimes', 'string', 'max:255', Rule::unique('inventory_items')->ignore($item->id)],
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('inventory_items')->ignore($item->id)],
            'category' => 'sometimes|string|max:255',
            'quantity' => 'nullable|numeric|min:0',
            'current_stock' => 'nullable|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'max_quantity' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'warehouse_id' => 'nullable|uuid|exists:warehouses,id',
        ]);
        
        $item->update($validated);
        
        return $this->success($item, 'Inventory item updated successfully');
    }

    /**
     * Delete an inventory item
     */
    public function destroy($id)
    {
        $companyId = $this->getCompanyId();
        $item = InventoryItem::where('company_id', $companyId)->findOrFail($id);
        
        // Check if item has stock movements
        if ($item->stockMovements()->count() > 0) {
            return $this->error('Cannot delete item with stock movement history', 422);
        }
        
        $item->delete();
        
        return $this->success(null, 'Inventory item deleted successfully');
    }

    /**
     * Adjust stock quantity
     */
    public function adjustStock(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $item = InventoryItem::where('company_id', $companyId)->findOrFail($id);
        
        $validated = $request->validate([
            'quantity' => 'required|numeric',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);
        
        $oldStock = $item->current_stock;
        $adjustment = $validated['quantity'];
        $newStock = $oldStock + $adjustment;
        
        if ($newStock < 0) {
            return $this->error('Insufficient stock. Current stock: ' . $oldStock, 422);
        }
        
        $item->current_stock = $newStock;
        $item->quantity = $newStock; // Keep quantity in sync
        $item->save();
        
        // Create stock movement record
        StockMovement::create([
            'company_id' => $companyId,
            'item_id' => $item->id,
            'type' => $adjustment > 0 ? 'in' : 'out',
            'quantity' => abs($adjustment),
            'from_location' => $adjustment < 0 ? $item->location : null,
            'to_location' => $adjustment > 0 ? $item->location : null,
            'reason' => $validated['reason'],
            'performed_by' => auth()->id(),
            'performed_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);
        
        return $this->success($item, 'Stock adjusted successfully');
    }

    /**
     * Get stock movements
     */
    public function movements(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = StockMovement::where('company_id', $companyId)
            ->with(['item', 'performer']);
        
        // Filter by item
        if ($request->has('item_id')) {
            $query->where('item_id', $request->input('item_id'));
        }
        
        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }
        
        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('performed_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('performed_at', '<=', $request->input('to_date'));
        }
        
        // Sort
        $query->orderBy('performed_at', 'desc');
        
        $perPage = $request->input('per_page', 15);
        $movements = $query->paginate($perPage);
        
        return $this->paginated($movements->items(), [
            'page' => $movements->currentPage(),
            'limit' => $movements->perPage(),
            'total' => $movements->total(),
            'totalPages' => $movements->lastPage(),
        ]);
    }

    /**
     * Get stock by location
     */
    public function stock(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = InventoryItem::where('company_id', $companyId);
        
        if ($request->has('location')) {
            $query->where('location', $request->input('location'));
        }

        $stock = $query->get();

        return $this->success($stock->toArray());
    }

    /**
     * Get low stock alerts
     */
    public function lowStockAlerts(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $items = InventoryItem::where('company_id', $companyId)
            ->whereColumn('current_stock', '<=', 'reorder_point')
            ->where('reorder_point', '>', 0)
            ->orderBy('current_stock', 'asc')
            ->get();
        
        return $this->success($items->toArray());
    }

    /**
     * Get stock transfers
     */
    public function transfers(Request $request)
    {
        $companyId = $this->getCompanyId();
        $transfers = StockMovement::where('company_id', $companyId)
            ->where('type', 'transfer')
            ->with('item', 'performer')
            ->paginate(10);
        
        return $this->paginated($transfers->items(), [
            'page' => $transfers->currentPage(),
            'limit' => $transfers->perPage(),
            'total' => $transfers->total(),
            'totalPages' => $transfers->lastPage(),
        ]);
    }

    /**
     * Transfer stock between locations (warehouse ↔ warehouse, warehouse ↔ van)
     */
    public function transfer(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $validated = $request->validate([
            'item_id' => 'required|uuid|exists:inventory_items,id',
            'quantity' => 'required|numeric|min:0.01',
            'from_location' => 'required|string|max:255',
            'to_location' => 'required|string|max:255',
            'from_type' => 'required|in:warehouse,van',
            'to_type' => 'required|in:warehouse,van',
            'to_technician_id' => 'required_if:to_type,van|uuid|exists:users,id',
            'notes' => 'nullable|string',
        ]);
        
        // Verify item belongs to company
        $item = InventoryItem::where('company_id', $companyId)
            ->findOrFail($validated['item_id']);
        
        DB::beginTransaction();
        try {
            // Handle source location (reduce stock)
            if ($validated['from_type'] === 'warehouse') {
                if ($item->current_stock < $validated['quantity']) {
                    DB::rollBack();
                    return $this->error('Insufficient stock in warehouse. Available: ' . $item->current_stock, 422);
                }
                $item->current_stock -= $validated['quantity'];
                $item->quantity = $item->current_stock;
                $item->save();
            } else { // from_type === 'van'
                $vanStock = VanStock::where('company_id', $companyId)
                    ->where('item_id', $validated['item_id'])
                    ->where('technician_id', $validated['from_location']) // from_location contains technician_id for van
                    ->firstOrFail();
                
                if ($vanStock->quantity < $validated['quantity']) {
                    DB::rollBack();
                    return $this->error('Insufficient stock in van. Available: ' . $vanStock->quantity, 422);
                }
                
                $vanStock->quantity -= $validated['quantity'];
                $vanStock->save();
            }
            
            // Handle destination location (add stock)
            if ($validated['to_type'] === 'warehouse') {
                $item->current_stock += $validated['quantity'];
                $item->quantity = $item->current_stock;
                $item->save();
            } else { // to_type === 'van'
                $vanStock = VanStock::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'item_id' => $validated['item_id'],
                        'technician_id' => $validated['to_technician_id'],
                    ],
                    [
                        'quantity' => DB::raw('COALESCE(quantity, 0) + ' . $validated['quantity']),
                    ]
                );
                
                if (!$vanStock->wasRecentlyCreated) {
                    $vanStock->refresh();
                } else {
                    $vanStock->quantity = $validated['quantity'];
                    $vanStock->save();
                }
            }
            
            // Create stock movement record
            StockMovement::create([
                'company_id' => $companyId,
                'item_id' => $validated['item_id'],
                'type' => 'transfer',
                'quantity' => $validated['quantity'],
                'from_location' => $validated['from_location'],
                'to_location' => $validated['to_type'] === 'van' 
                    ? 'van-' . $validated['to_technician_id'] 
                    : $validated['to_location'],
                'reason' => 'Stock transfer',
                'performed_by' => auth()->id(),
                'performed_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);
            
            DB::commit();
            
            return $this->success([
                'item' => $item->fresh(),
                'message' => 'Stock transferred successfully',
            ], 'Stock transferred successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to transfer stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Issue stock to a job
     */
    public function issueToJob(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $validated = $request->validate([
            'job_id' => 'required|uuid|exists:jobs,id',
            'item_id' => 'required|uuid|exists:inventory_items,id',
            'quantity' => 'required|numeric|min:0.01',
            'source_type' => 'required|in:warehouse,van',
            'source_id' => 'required|string', // warehouse_id or technician_id for van
            'notes' => 'nullable|string',
        ]);
        
        // Verify job belongs to company
        $job = \App\Models\Job::where('company_id', $companyId)->findOrFail($validated['job_id']);
        
        // Verify item belongs to company
        $item = InventoryItem::where('company_id', $companyId)
            ->findOrFail($validated['item_id']);
        
        DB::beginTransaction();
        try {
            // Reduce stock from source
            if ($validated['source_type'] === 'warehouse') {
                if ($item->current_stock < $validated['quantity']) {
                    DB::rollBack();
                    return $this->error('Insufficient stock in warehouse. Available: ' . $item->current_stock, 422);
                }
                $item->current_stock -= $validated['quantity'];
                $item->quantity = $item->current_stock;
                $item->save();
            } else { // source_type === 'van'
                $vanStock = VanStock::where('company_id', $companyId)
                    ->where('item_id', $validated['item_id'])
                    ->where('technician_id', $validated['source_id'])
                    ->firstOrFail();
                
                if ($vanStock->quantity < $validated['quantity']) {
                    DB::rollBack();
                    return $this->error('Insufficient stock in van. Available: ' . $vanStock->quantity, 422);
                }
                
                $vanStock->quantity -= $validated['quantity'];
                $vanStock->save();
            }
            
            // Create job material record
            $unitCost = $item->cost_price ?? $item->unit_price ?? 0;
            $totalCost = $validated['quantity'] * $unitCost;
            
            $jobMaterial = \App\Models\JobMaterial::create([
                'company_id' => $companyId,
                'job_id' => $validated['job_id'],
                'item_id' => $validated['item_id'],
                'issued_from' => $validated['source_id'],
                'issued_from_type' => $validated['source_type'],
                'quantity' => $validated['quantity'],
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'status' => 'issued',
                'issued_by' => auth()->id(),
                'issued_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);
            
            // Create stock movement record
            StockMovement::create([
                'company_id' => $companyId,
                'item_id' => $validated['item_id'],
                'type' => 'out',
                'quantity' => $validated['quantity'],
                'from_location' => $validated['source_type'] === 'warehouse' 
                    ? 'warehouse-' . $validated['source_id']
                    : 'van-' . $validated['source_id'],
                'to_location' => 'job-' . $validated['job_id'],
                'reason' => 'Issued to job',
                'reference' => $jobMaterial->id,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);
            
            // Update job material cost
            $job->material_cost = $job->jobMaterials()->sum('total_cost');
            $job->save();
            
            DB::commit();
            
            return $this->success($jobMaterial->load(['item', 'job', 'issuer']), 'Stock issued to job successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to issue stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Return stock from a job
     */
    public function returnFromJob(Request $request, $jobMaterialId)
    {
        $companyId = $this->getCompanyId();
        $jobMaterial = \App\Models\JobMaterial::where('company_id', $companyId)
            ->findOrFail($jobMaterialId);
        
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'return_to_type' => 'required|in:warehouse,van',
            'return_to_id' => 'required|string', // warehouse_id or technician_id for van
            'status' => 'required|in:returned,wasted',
            'notes' => 'nullable|string',
        ]);
        
        if ($validated['quantity'] > $jobMaterial->quantity) {
            return $this->error('Cannot return more than issued quantity', 422);
        }
        
        if ($jobMaterial->status === 'returned') {
            return $this->error('Material already returned', 422);
        }
        
        DB::beginTransaction();
        try {
            // Add stock back to destination
            $item = $jobMaterial->item;
            
            if ($validated['return_to_type'] === 'warehouse') {
                $item->current_stock += $validated['quantity'];
                $item->quantity = $item->current_stock;
                $item->save();
            } else { // return_to_type === 'van'
                $vanStock = VanStock::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'item_id' => $jobMaterial->item_id,
                        'technician_id' => $validated['return_to_id'],
                    ],
                    [
                        'quantity' => DB::raw('COALESCE(quantity, 0) + ' . $validated['quantity']),
                    ]
                );
                
                if (!$vanStock->wasRecentlyCreated) {
                    $vanStock->refresh();
                } else {
                    $vanStock->quantity = $validated['quantity'];
                    $vanStock->save();
                }
            }
            
            // Update job material status
            $jobMaterial->status = $validated['status'];
            $jobMaterial->returned_at = now();
            if ($validated['notes']) {
                $jobMaterial->notes = ($jobMaterial->notes ? $jobMaterial->notes . "\n" : '') . $validated['notes'];
            }
            $jobMaterial->save();
            
            // Create stock movement record
            StockMovement::create([
                'company_id' => $companyId,
                'item_id' => $jobMaterial->item_id,
                'type' => 'in',
                'quantity' => $validated['quantity'],
                'from_location' => 'job-' . $jobMaterial->job_id,
                'to_location' => $validated['return_to_type'] === 'warehouse'
                    ? 'warehouse-' . $validated['return_to_id']
                    : 'van-' . $validated['return_to_id'],
                'reason' => $validated['status'] === 'returned' ? 'Returned from job' : 'Wasted from job',
                'reference' => $jobMaterial->id,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);
            
            // Update job material cost (reduce if returned)
            $job = $jobMaterial->job;
            if ($validated['status'] === 'returned') {
                $returnedCost = $validated['quantity'] * $jobMaterial->unit_cost;
                $job->material_cost = max(0, ($job->material_cost ?? 0) - $returnedCost);
            }
            $job->save();
            
            DB::commit();
            
            return $this->success($jobMaterial->load(['item', 'job', 'issuer']), 'Stock returned from job successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to return stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get materials used in a job
     */
    public function getJobMaterials($jobId)
    {
        $companyId = $this->getCompanyId();
        
        // Verify job belongs to company
        $job = \App\Models\Job::where('company_id', $companyId)->findOrFail($jobId);
        
        $materials = $job->jobMaterials()
            ->with(['item', 'issuer'])
            ->get();
        
        return $this->success($materials->toArray());
    }

    /**
     * Create a stock audit
     */
    public function createAudit(Request $request)
    {
        $companyId = $this->getCompanyId();
        $userId = auth()->id();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'item_id' => 'required|uuid|exists:inventory_items,id',
            'warehouse_id' => 'nullable|uuid|exists:warehouses,id',
            'expected_quantity' => 'required|numeric|min:0',
            'actual_quantity' => 'required|numeric|min:0',
            'reason' => 'nullable|string|in:damaged,lost,theft,error,other',
            'notes' => 'nullable|string|max:1000',
            'adjust_stock' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        // Verify item belongs to company
        $item = InventoryItem::where('company_id', $companyId)
            ->findOrFail($request->input('item_id'));

        // Verify warehouse if provided
        if ($request->filled('warehouse_id')) {
            $warehouse = \App\Models\Warehouse::where('company_id', $companyId)
                ->find($request->input('warehouse_id'));
            if (!$warehouse) {
                return $this->error('Warehouse not found or does not belong to your company', 422);
            }
        }

        $expectedQuantity = $request->input('expected_quantity');
        $actualQuantity = $request->input('actual_quantity');
        $variance = $actualQuantity - $expectedQuantity;

        DB::beginTransaction();
        try {
            // Create audit record
            $audit = StockAudit::create([
                'company_id' => $companyId,
                'item_id' => $item->id,
                'warehouse_id' => $request->input('warehouse_id'),
                'expected_quantity' => $expectedQuantity,
                'actual_quantity' => $actualQuantity,
                'variance' => $variance,
                'reason' => $request->input('reason'),
                'notes' => $request->input('notes'),
                'audited_by' => $userId,
                'audited_at' => now(),
                'adjusted' => $request->input('adjust_stock', false),
            ]);

            // Adjust stock if requested
            if ($request->input('adjust_stock', false)) {
                $oldStock = $item->current_stock;
                $newStock = $actualQuantity;
                $item->current_stock = $newStock;
                $item->last_audit_date = now();
                $item->save();

                // Create stock movement for the adjustment
                StockMovement::create([
                    'company_id' => $companyId,
                    'item_id' => $item->id,
                    'type' => $variance > 0 ? 'in' : 'out',
                    'quantity' => abs($variance),
                    'from_location' => $variance < 0 ? 'warehouse' : null,
                    'to_location' => $variance > 0 ? 'warehouse' : null,
                    'reason' => 'Stock audit adjustment',
                    'reference' => $audit->id,
                    'performed_by' => $userId,
                    'performed_at' => now(),
                    'notes' => "Audit adjustment: Expected {$expectedQuantity}, Actual {$actualQuantity}. " . ($request->input('notes') ?? ''),
                ]);
            } else {
                // Just update last audit date
                $item->last_audit_date = now();
                $item->save();
            }

            DB::commit();

            return $this->success(
                $audit->load(['item', 'warehouse', 'auditor'])->toArray(),
                'Stock audit created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Stock audit creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Failed to create stock audit: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock audits with filtering
     */
    public function getAudits(Request $request)
    {
        $companyId = $this->getCompanyId();

        $query = StockAudit::where('company_id', $companyId)
            ->with(['item', 'warehouse', 'auditor']);

        // Filter by item
        if ($request->has('item_id')) {
            $query->where('item_id', $request->input('item_id'));
        }

        // Filter by warehouse
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('audited_at', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('audited_at', '<=', $request->input('end_date'));
        }

        // Filter by variance (discrepancies)
        if ($request->has('has_variance') && $request->input('has_variance') === 'true') {
            $query->where('variance', '!=', 0);
        }

        // Sort
        $sortField = $request->input('sort_field', 'audited_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->input('per_page', 15);
        $audits = $query->paginate($perPage);

        return $this->paginated($audits->items(), [
            'total' => $audits->total(),
            'per_page' => $audits->perPage(),
            'current_page' => $audits->currentPage(),
            'last_page' => $audits->lastPage(),
        ]);
    }

    /**
     * Get audit statistics
     */
    public function getAuditStats(Request $request)
    {
        $companyId = $this->getCompanyId();

        $query = StockAudit::where('company_id', $companyId);

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('audited_at', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('audited_at', '<=', $request->input('end_date'));
        }

        $totalAudits = $query->count();
        $auditsWithVariance = (clone $query)->where('variance', '!=', 0)->count();
        $totalVariance = (clone $query)->sum('variance');
        $totalVarianceValue = (clone $query)
            ->join('inventory_items', 'stock_audits.item_id', '=', 'inventory_items.id')
            ->selectRaw('SUM(stock_audits.variance * inventory_items.cost_price) as total')
            ->value('total') ?? 0;

        return $this->success([
            'total_audits' => $totalAudits,
            'audits_with_variance' => $auditsWithVariance,
            'total_variance_quantity' => $totalVariance,
            'total_variance_value' => $totalVarianceValue,
            'accuracy_rate' => $totalAudits > 0 ? (($totalAudits - $auditsWithVariance) / $totalAudits) * 100 : 0,
        ]);
    }
}

