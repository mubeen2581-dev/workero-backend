<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /**
     * Display a listing of clients.
     */
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = Client::where('company_id', $companyId);

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by tags
        if ($request->has('tags')) {
            $tags = is_array($request->input('tags')) 
                ? $request->input('tags') 
                : explode(',', $request->input('tags'));
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        // Filter by lead score range
        if ($request->has('minLeadScore')) {
            $query->where('lead_score', '>=', $request->input('minLeadScore'));
        }
        if ($request->has('maxLeadScore')) {
            $query->where('lead_score', '<=', $request->input('maxLeadScore'));
        }

        // Sort
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $allowedSortFields = ['name', 'email', 'created_at', 'lead_score'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);
        $clients = $query->paginate($limit, ['*'], 'page', $page);

        return $this->paginated(
            $clients->items(),
            [
                'page' => $clients->currentPage(),
                'limit' => $clients->perPage(),
                'total' => $clients->total(),
                'totalPages' => $clients->lastPage(),
            ]
        );
    }

    /**
     * Store a newly created client.
     */
    public function store(Request $request)
    {
        $companyId = $this->getCompanyId();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                \Illuminate\Validation\Rule::unique('clients', 'email')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                }),
            ],
            'phone' => 'required|string',
            'address' => 'required|array',
            'address.street' => 'required|string',
            'address.city' => 'required|string',
            'address.state' => 'required|string',
            'address.zipCode' => 'required|string',
            'address.country' => 'required|string',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $client = Client::create([
            'company_id' => $companyId,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'tags' => $request->input('tags', []),
            'lead_score' => 0,
        ]);

        return $this->success($client->toArray(), 'Client created successfully', 201);
    }

    /**
     * Display the specified client.
     */
    public function show($id)
    {
        $companyId = $this->getCompanyId();

        $client = Client::where('company_id', $companyId)->findOrFail($id);

        return $this->success($client->toArray());
    }

    /**
     * Update the specified client.
     */
    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                \Illuminate\Validation\Rule::unique('clients', 'email')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                })->ignore($id),
            ],
            'phone' => 'sometimes|string',
            'address' => 'sometimes|array',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $client = Client::where('company_id', $companyId)->findOrFail($id);

        $updateData = $request->only(['name', 'email', 'phone', 'address', 'tags']);
        if ($request->has('leadScore')) {
            $updateData['lead_score'] = $request->input('leadScore');
        }

        $client->update($updateData);

        return $this->success($client->fresh()->toArray(), 'Client updated successfully');
    }

    /**
     * Remove the specified client.
     */
    public function destroy($id)
    {
        $companyId = $this->getCompanyId();
        $client = Client::where('company_id', $companyId)->findOrFail($id);

        $client->delete();

        return $this->success(null, 'Client deleted successfully');
    }
}

