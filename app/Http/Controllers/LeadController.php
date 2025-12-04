<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadActivity;
use App\Services\LeadDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $needsJoin = $request->input('sortBy') === 'client_name' || $request->input('sort') === 'client_name';
        
        $query = Lead::where('leads.company_id', $companyId);
        
        if ($needsJoin) {
            $query->join('clients', 'leads.client_id', '=', 'clients.id')
                  ->select('leads.*');
        }
        
        $query->with('client', 'assignedUser');

        // Search by client name, email, or phone
        if ($request->has('search')) {
            $search = $request->input('search');
            if ($needsJoin) {
                $query->where(function($q) use ($search) {
                    $q->where('clients.name', 'like', "%{$search}%")
                      ->orWhere('clients.email', 'like', "%{$search}%")
                      ->orWhere('clients.phone', 'like', "%{$search}%")
                      ->orWhere('leads.notes', 'like', "%{$search}%");
                });
            } else {
                $query->whereHas('client', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                })->orWhere('leads.notes', 'like', "%{$search}%");
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('leads.status', $request->input('status'));
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('leads.priority', $request->input('priority'));
        }

        // Filter by source
        if ($request->has('source')) {
            $query->where('leads.source', $request->input('source'));
        }

        // Filter by assigned user (supports UUID, email, or name)
        if ($request->has('assignedTo')) {
            $assignedTo = trim($request->input('assignedTo', ''));
            
            if (!empty($assignedTo)) {
                // Check if it's a UUID
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $assignedTo)) {
                    // It's a UUID, use direct match
                    $query->where('leads.assigned_to', $assignedTo);
                } else {
                    // It's likely an email or name, search in users table
                    $query->whereHas('assignedUser', function($q) use ($assignedTo) {
                        $q->where('email', 'like', "%{$assignedTo}%")
                          ->orWhere('first_name', 'like', "%{$assignedTo}%")
                          ->orWhere('last_name', 'like', "%{$assignedTo}%")
                          ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$assignedTo}%"]);
                    });
                }
            }
        }

        // Filter by unassigned
        if ($request->has('unassigned') && $request->input('unassigned') === 'true') {
            $query->whereNull('leads.assigned_to');
        }

        // Filter by date range
        if ($request->has('dateFrom')) {
            $query->whereDate('leads.created_at', '>=', $request->input('dateFrom'));
        }
        if ($request->has('dateTo')) {
            $query->whereDate('leads.created_at', '<=', $request->input('dateTo'));
        }

        // Sort
        $sortField = $request->input('sortBy') ?? $request->input('sort', 'created_at');
        $sortDirection = $request->input('sortOrder') ?? $request->input('direction', 'desc');
        $allowedSortFields = ['created_at', 'estimated_value', 'status', 'priority', 'client_name'];
        if (in_array($sortField, $allowedSortFields)) {
            if ($sortField === 'client_name') {
                $query->orderBy('clients.name', $sortDirection);
            } else {
                $query->orderBy('leads.' . $sortField, $sortDirection);
            }
        }

        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);
        $leads = $query->paginate($limit, ['*'], 'page', $page);

        return $this->paginated(
            $leads->items(),
            [
                'page' => $leads->currentPage(),
                'limit' => $leads->perPage(),
                'total' => $leads->total(),
                'totalPages' => $leads->lastPage(),
            ]
        );
    }

    public function store(Request $request)
    {
        $companyId = $this->getCompanyId();
        $clientId = null;

        // Check if clientId is provided, or if we need to create a client
        if ($request->has('clientId')) {
            // Use existing client
            $validator = Validator::make($request->all(), [
                'clientId' => 'required|uuid|exists:clients,id',
                'source' => 'required|in:website,referral,advertisement,cold_call,other',
                'status' => 'sometimes|in:new,contacted,qualified,quoted,converted,lost',
                'priority' => 'sometimes|in:low,medium,high,urgent',
                'estimatedValue' => 'sometimes|numeric|min:0',
                'notes' => 'nullable|string',
                'assignedTo' => 'nullable|uuid|exists:users,id',
            ]);

            if ($validator->fails()) {
                return $this->error('Validation error', $validator->errors(), 422);
            }

            // Verify client belongs to company
            $client = Client::where('company_id', $companyId)
                ->findOrFail($request->input('clientId'));
            $clientId = $client->id;
        } elseif ($request->has('client')) {
            // Create new client from provided data
            $clientData = $request->input('client');
            $clientValidator = Validator::make($clientData, [
                'name' => 'required|string|max:255',
                'email' => [
                    'required',
                    'email',
                    \Illuminate\Validation\Rule::unique('clients', 'email')->where(function ($query) use ($companyId) {
                        return $query->where('company_id', $companyId);
                    }),
                ],
                'phone' => 'required|string',
                'address' => 'nullable|array',
                'address.street' => 'nullable|string',
                'address.city' => 'nullable|string',
                'address.state' => 'nullable|string',
                'address.zipCode' => 'nullable|string',
                'address.country' => 'nullable|string',
                'tags' => 'nullable|array',
            ]);

            if ($clientValidator->fails()) {
                return $this->error('Client validation error', $clientValidator->errors(), 422);
            }

            // Create client
            $client = Client::create([
                'company_id' => $companyId,
                'name' => $clientData['name'],
                'email' => $clientData['email'],
                'phone' => $clientData['phone'],
                'address' => $clientData['address'] ?? [
                    'street' => '',
                    'city' => '',
                    'state' => '',
                    'zipCode' => '',
                    'country' => 'US',
                ],
                'tags' => $clientData['tags'] ?? [],
                'lead_score' => 0,
            ]);
            $clientId = $client->id;
        } else {
            // Support legacy format: name, email, phone at root level
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
                'source' => 'required|in:website,referral,advertisement,cold_call,other',
                'status' => 'sometimes|in:new,contacted,qualified,quoted,converted,lost',
                'priority' => 'sometimes|in:low,medium,high,urgent',
                'estimatedValue' => 'sometimes|numeric|min:0',
                'notes' => 'nullable|string',
                'tags' => 'nullable|array',
                'assignedTo' => 'nullable|uuid|exists:users,id',
            ]);

            if ($validator->fails()) {
                return $this->error('Validation error', $validator->errors(), 422);
            }

            // Create client from root level data
            $client = Client::create([
                'company_id' => $companyId,
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'address' => [
                    'street' => '',
                    'city' => '',
                    'state' => '',
                    'zipCode' => '',
                    'country' => 'US',
                ],
                'tags' => $request->input('tags', []),
                'lead_score' => 0,
            ]);
            $clientId = $client->id;
        }

        // Validate lead-specific fields
        $leadValidator = Validator::make($request->all(), [
            'source' => 'required|in:website,referral,advertisement,cold_call,other',
            'status' => 'sometimes|in:new,contacted,qualified,quoted,converted,lost',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'estimatedValue' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
            'assignedTo' => 'nullable|uuid|exists:users,id',
        ]);

        if ($leadValidator->fails()) {
            return $this->error('Lead validation error', $leadValidator->errors(), 422);
        }

        $lead = Lead::create([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'source' => $request->input('source'),
            'status' => $request->input('status', 'new'),
            'priority' => $request->input('priority', 'medium'),
            'estimated_value' => $request->input('estimatedValue', 0),
            'notes' => $request->input('notes'),
            'assigned_to' => $request->input('assignedTo'),
        ]);

        // Create activity log
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'created',
            'description' => 'Lead created',
            'metadata' => [
                'source' => $lead->source,
                'status' => $lead->status,
                'priority' => $lead->priority,
            ],
        ]);

        return $this->success($lead->load('client', 'assignedUser')->toArray(), 'Lead created successfully', 201);
    }

    public function show($id)
    {
        $companyId = $this->getCompanyId();
        $lead = Lead::where('company_id', $companyId)
            ->with('client', 'assignedUser', 'activities.user')
            ->findOrFail($id);

        return $this->success($lead->toArray());
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:new,contacted,qualified,quoted,converted,lost',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'estimatedValue' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
            'assignedTo' => 'nullable|uuid|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();
        $lead = Lead::where('company_id', $companyId)->findOrFail($id);

        $oldStatus = $lead->status;
        $oldAssignedTo = $lead->assigned_to;

        $data = [];
        if ($request->has('status')) $data['status'] = $request->input('status');
        if ($request->has('priority')) $data['priority'] = $request->input('priority');
        if ($request->has('estimatedValue')) $data['estimated_value'] = $request->input('estimatedValue');
        if ($request->has('notes')) $data['notes'] = $request->input('notes');
        if ($request->has('assignedTo')) $data['assigned_to'] = $request->input('assignedTo');

        $lead->update($data);
        $lead->refresh();

        // Create activity logs for changes
        if ($request->has('status') && $oldStatus !== $lead->status) {
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'status_changed',
                'description' => "Status changed from {$oldStatus} to {$lead->status}",
                'metadata' => [
                    'old_status' => $oldStatus,
                    'new_status' => $lead->status,
                ],
            ]);
        }

        if ($request->has('assignedTo') && $oldAssignedTo !== $lead->assigned_to) {
            $assignedUser = $lead->assignedUser;
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'assigned',
                'description' => $lead->assigned_to 
                    ? "Assigned to {$assignedUser->first_name} {$assignedUser->last_name}"
                    : 'Unassigned',
                'metadata' => [
                    'old_assigned_to' => $oldAssignedTo,
                    'new_assigned_to' => $lead->assigned_to,
                ],
            ]);
        }

        if ($request->has('notes') && !empty($request->input('notes'))) {
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'note_added',
                'description' => 'Note added',
                'metadata' => [
                    'note' => $request->input('notes'),
                ],
            ]);
        }

        // Generic update activity if other fields changed
        if (count($data) > 0 && !$request->has('status') && !$request->has('assignedTo') && !$request->has('notes')) {
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'updated',
                'description' => 'Lead updated',
                'metadata' => $data,
            ]);
        }

        return $this->success($lead->fresh()->load('client', 'assignedUser')->toArray(), 'Lead updated successfully');
    }

    public function destroy($id)
    {
        $companyId = $this->getCompanyId();
        $lead = Lead::where('company_id', $companyId)->findOrFail($id);

        $lead->delete();

        return $this->success(null, 'Lead deleted successfully');
    }

    /**
     * Get activities for a lead
     */
    public function activities($id)
    {
        $companyId = $this->getCompanyId();
        $lead = Lead::where('company_id', $companyId)->findOrFail($id);

        $activities = LeadActivity::where('lead_id', $lead->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($activities->toArray());
    }

    /**
     * Update lead status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:new,contacted,qualified,quoted,converted,lost',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();
        $lead = Lead::where('company_id', $companyId)->findOrFail($id);

        $oldStatus = $lead->status;
        $lead->status = $request->input('status');
        $lead->save();

        // Create activity log
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'status_changed',
            'description' => "Status changed from {$oldStatus} to {$lead->status}",
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $lead->status,
            ],
        ]);

        return $this->success($lead->fresh()->load('client', 'assignedUser')->toArray(), 'Lead status updated successfully');
    }

    /**
     * Assign lead to user
     */
    public function assign(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'assignedTo' => 'required|uuid|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();
        $lead = Lead::where('company_id', $companyId)->findOrFail($id);

        // Verify assigned user belongs to same company
        $assignedUser = \App\Models\User::where('company_id', $companyId)
            ->findOrFail($request->input('assignedTo'));

        $oldAssignedTo = $lead->assigned_to;
        $lead->assigned_to = $request->input('assignedTo');
        $lead->save();

        // Create activity log
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'assigned',
            'description' => "Assigned to {$assignedUser->first_name} {$assignedUser->last_name}",
            'metadata' => [
                'old_assigned_to' => $oldAssignedTo,
                'new_assigned_to' => $lead->assigned_to,
                'assigned_user_name' => "{$assignedUser->first_name} {$assignedUser->last_name}",
            ],
        ]);

        return $this->success($lead->fresh()->load('client', 'assignedUser')->toArray(), 'Lead assigned successfully');
    }

    /**
     * Distribute multiple leads automatically
     */
    public function distribute(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leadIds' => 'required|array',
            'leadIds.*' => 'required|uuid|exists:leads,id',
            'method' => 'required|in:round_robin,workload,priority',
            'role' => 'nullable|in:admin,manager,dispatcher',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        try {
            $distributionService = new LeadDistributionService();
            $method = $request->input('method');
            $role = $request->input('role');

            switch ($method) {
                case 'round_robin':
                    $assignments = $distributionService->distributeRoundRobin(
                        $request->input('leadIds'),
                        $role
                    );
                    break;
                case 'workload':
                    $assignments = $distributionService->distributeByWorkload(
                        $request->input('leadIds'),
                        $role
                    );
                    break;
                case 'priority':
                    $assignments = $distributionService->distributeByPriority(
                        $request->input('leadIds'),
                        $role
                    );
                    break;
                default:
                    return $this->error('Invalid distribution method', [], 422);
            }

            // Create activity logs for each assignment
            foreach ($assignments as $assignment) {
                $lead = Lead::find($assignment['lead_id']);
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => auth()->id(),
                    'type' => 'assigned',
                    'description' => "Auto-assigned via {$method} distribution to {$assignment['user_name']}",
                    'metadata' => [
                        'distribution_method' => $method,
                        'assigned_to' => $assignment['assigned_to'],
                        'assigned_user_name' => $assignment['user_name'],
                    ],
                ]);
            }

            return $this->success([
                'assignments' => $assignments,
                'total_assigned' => count($assignments),
                'method' => $method,
            ], 'Leads distributed successfully');
        } catch (\Exception $e) {
            return $this->error('Distribution failed', ['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user workload statistics
     */
    public function workloads(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'nullable|in:admin,manager,dispatcher',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        try {
            $distributionService = new LeadDistributionService();
            $workloads = $distributionService->getUserWorkloads($request->input('role'));

            return $this->success($workloads, 'Workload statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve workloads', ['message' => $e->getMessage()], 500);
        }
    }
}

