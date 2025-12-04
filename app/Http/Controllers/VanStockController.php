<?php

namespace App\Http\Controllers;

use App\Models\VanStock;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VanStockController extends Controller
{
    /**
     * Get all van stock with filtering
     */
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = VanStock::where('van_stock.company_id', $companyId)
            ->with(['item', 'technician']);
        
        // Filter by technician
        if ($request->has('technician_id')) {
            $query->where('technician_id', $request->input('technician_id'));
        }
        
        // Filter by item
        if ($request->has('item_id')) {
            $query->where('item_id', $request->input('item_id'));
        }
        
        $perPage = $request->input('per_page', 15);
        $vanStock = $query->paginate($perPage);
        
        return $this->paginated($vanStock->items(), [
            'page' => $vanStock->currentPage(),
            'limit' => $vanStock->perPage(),
            'total' => $vanStock->total(),
            'totalPages' => $vanStock->lastPage(),
        ]);
    }

    /**
     * Get van stock for a specific technician
     */
    public function getByTechnician($technicianId)
    {
        $companyId = $this->getCompanyId();
        
        $vanStock = VanStock::where('company_id', $companyId)
            ->where('technician_id', $technicianId)
            ->with(['item'])
            ->get();
        
        return $this->success($vanStock->toArray());
    }

    /**
     * Assign stock to a van/technician
     */
    public function assign(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $validated = $request->validate([
            'item_id' => 'required|uuid|exists:inventory_items,id',
            'technician_id' => 'required|uuid|exists:users,id',
            'quantity' => 'required|numeric|min:0.01',
            'from_location' => 'required|string|max:255', // warehouse or another van
            'notes' => 'nullable|string',
        ]);
        
        // Verify item belongs to company
        $item = InventoryItem::where('company_id', $companyId)
            ->findOrFail($validated['item_id']);
        
        // Check if stock is available
        if ($validated['from_location'] === 'warehouse' || str_contains($validated['from_location'], 'warehouse')) {
            if ($item->current_stock < $validated['quantity']) {
                return $this->error('Insufficient stock in warehouse. Available: ' . $item->current_stock, 422);
            }
        }
        
        DB::beginTransaction();
        try {
            // Update or create van stock record
            $vanStock = VanStock::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'item_id' => $validated['item_id'],
                    'technician_id' => $validated['technician_id'],
                ],
                [
                    'quantity' => DB::raw('quantity + ' . $validated['quantity']),
                ]
            );
            
            // If it was a new record, set the initial quantity
            if (!$vanStock->wasRecentlyCreated) {
                $vanStock->refresh();
            } else {
                $vanStock->quantity = $validated['quantity'];
                $vanStock->save();
            }
            
            // Reduce stock from source location
            if ($validated['from_location'] === 'warehouse' || str_contains($validated['from_location'], 'warehouse')) {
                $item->current_stock -= $validated['quantity'];
                $item->quantity = $item->current_stock;
                $item->save();
            }
            
            // Create stock movement record
            StockMovement::create([
                'company_id' => $companyId,
                'item_id' => $validated['item_id'],
                'type' => 'transfer',
                'quantity' => $validated['quantity'],
                'from_location' => $validated['from_location'],
                'to_location' => 'van-' . $validated['technician_id'],
                'reason' => 'Stock assigned to van',
                'performed_by' => auth()->id(),
                'performed_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);
            
            DB::commit();
            
            return $this->success($vanStock->load(['item', 'technician']), 'Stock assigned to van successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to assign stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Return stock from van to warehouse
     */
    public function return(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $vanStock = VanStock::where('company_id', $companyId)->findOrFail($id);
        
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'to_location' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);
        
        if ($vanStock->quantity < $validated['quantity']) {
            return $this->error('Insufficient stock in van. Available: ' . $vanStock->quantity, 422);
        }
        
        DB::beginTransaction();
        try {
            // Reduce van stock
            $vanStock->quantity -= $validated['quantity'];
            $vanStock->save();
            
            // Add stock to destination
            $item = $vanStock->item;
            $item->current_stock += $validated['quantity'];
            $item->quantity = $item->current_stock;
            $item->save();
            
            // Create stock movement record
            StockMovement::create([
                'company_id' => $companyId,
                'item_id' => $vanStock->item_id,
                'type' => 'transfer',
                'quantity' => $validated['quantity'],
                'from_location' => 'van-' . $vanStock->technician_id,
                'to_location' => $validated['to_location'],
                'reason' => 'Stock returned from van',
                'performed_by' => auth()->id(),
                'performed_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);
            
            DB::commit();
            
            return $this->success($vanStock->load(['item', 'technician']), 'Stock returned successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to return stock: ' . $e->getMessage(), 500);
        }
    }
}

