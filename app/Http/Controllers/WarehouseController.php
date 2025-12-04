<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    /**
     * Get all warehouses with filtering and pagination
     */
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = Warehouse::where('company_id', $companyId);
        
        // Search by name or code
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        
        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        // Sort
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);
        
        $perPage = $request->input('per_page', 15);
        $warehouses = $query->paginate($perPage);
        
        return $this->paginated($warehouses->items(), [
            'page' => $warehouses->currentPage(),
            'limit' => $warehouses->perPage(),
            'total' => $warehouses->total(),
            'totalPages' => $warehouses->lastPage(),
        ]);
    }

    /**
     * Get a single warehouse by ID
     */
    public function show($id)
    {
        $companyId = $this->getCompanyId();
        $warehouse = Warehouse::where('company_id', $companyId)
            ->with('inventoryItems')
            ->findOrFail($id);
        
        return $this->success($warehouse);
    }

    /**
     * Create a new warehouse
     */
    public function store(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:warehouses,code',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);
        
        $validated['company_id'] = $companyId;
        $validated['is_active'] = $validated['is_active'] ?? true;
        
        $warehouse = Warehouse::create($validated);
        
        return $this->success($warehouse, 'Warehouse created successfully', 201);
    }

    /**
     * Update an existing warehouse
     */
    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $warehouse = Warehouse::where('company_id', $companyId)->findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('warehouses')->ignore($warehouse->id)],
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);
        
        $warehouse->update($validated);
        
        return $this->success($warehouse, 'Warehouse updated successfully');
    }

    /**
     * Delete a warehouse
     */
    public function destroy($id)
    {
        $companyId = $this->getCompanyId();
        $warehouse = Warehouse::where('company_id', $companyId)->findOrFail($id);
        
        // Check if warehouse has inventory items
        if ($warehouse->inventoryItems()->count() > 0) {
            return $this->error('Cannot delete warehouse with inventory items', 422);
        }
        
        $warehouse->delete();
        
        return $this->success(null, 'Warehouse deleted successfully');
    }

    /**
     * Get warehouse stock levels
     */
    public function stock($id)
    {
        $companyId = $this->getCompanyId();
        $warehouse = Warehouse::where('company_id', $companyId)->findOrFail($id);
        
        $items = $warehouse->inventoryItems()
            ->select('inventory_items.*')
            ->get();
        
        return $this->success([
            'warehouse' => $warehouse,
            'items' => $items,
            'total_items' => $items->count(),
            'total_value' => $items->sum(function($item) {
                return $item->current_stock * $item->cost_price;
            }),
        ]);
    }
}

