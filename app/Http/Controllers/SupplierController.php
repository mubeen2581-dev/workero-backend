<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Get all suppliers with filtering and pagination
     */
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = Supplier::where('company_id', $companyId);
        
        // Search by name, email, or phone
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
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
        $suppliers = $query->paginate($perPage);
        
        return $this->paginated($suppliers->items(), [
            'page' => $suppliers->currentPage(),
            'limit' => $suppliers->perPage(),
            'total' => $suppliers->total(),
            'totalPages' => $suppliers->lastPage(),
        ]);
    }

    /**
     * Get a single supplier by ID
     */
    public function show($id)
    {
        $companyId = $this->getCompanyId();
        $supplier = Supplier::where('company_id', $companyId)->findOrFail($id);
        
        return $this->success($supplier);
    }

    /**
     * Create a new supplier
     */
    public function store(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        
        $validated['company_id'] = $companyId;
        $validated['is_active'] = $validated['is_active'] ?? true;
        
        $supplier = Supplier::create($validated);
        
        return $this->success($supplier, 'Supplier created successfully', 201);
    }

    /**
     * Update an existing supplier
     */
    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $supplier = Supplier::where('company_id', $companyId)->findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        
        $supplier->update($validated);
        
        return $this->success($supplier, 'Supplier updated successfully');
    }

    /**
     * Delete a supplier
     */
    public function destroy($id)
    {
        $companyId = $this->getCompanyId();
        $supplier = Supplier::where('company_id', $companyId)->findOrFail($id);
        
        $supplier->delete();
        
        return $this->success(null, 'Supplier deleted successfully');
    }
}

