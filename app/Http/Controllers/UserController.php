<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = User::where('company_id', $companyId);

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->has('team')) {
            $query->where('team', $request->input('team'));
        }

        if ($request->has('region')) {
            $query->where('region', $request->input('region'));
        }

        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);
        $users = $query->paginate($limit, ['*'], 'page', $page);

        return $this->paginated(
            $users->items(),
            [
                'page' => $users->currentPage(),
                'limit' => $users->perPage(),
                'total' => $users->total(),
                'totalPages' => $users->lastPage(),
            ]
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'role' => 'required|in:admin,manager,technician,dispatcher',
            'skills' => 'nullable|array',
            'team' => 'nullable|string',
            'region' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();

        $user = User::create([
            'company_id' => $companyId,
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'first_name' => $request->input('firstName'),
            'last_name' => $request->input('lastName'),
            'role' => $request->input('role'),
            'skills' => $request->input('skills', []),
            'team' => $request->input('team'),
            'region' => $request->input('region'),
        ]);

        return $this->success($user->toArray(), 'User created successfully', 201);
    }

    public function show($id)
    {
        $companyId = $this->getCompanyId();
        $user = User::where('company_id', $companyId)->findOrFail($id);

        return $this->success($user->toArray());
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:6',
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'role' => 'sometimes|in:admin,manager,technician,dispatcher',
            'skills' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();
        $user = User::where('company_id', $companyId)->findOrFail($id);

        $data = $request->only(['email', 'firstName', 'lastName', 'role', 'skills', 'team', 'region']);
        
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        // Map camelCase to snake_case
        if (isset($data['firstName'])) {
            $data['first_name'] = $data['firstName'];
            unset($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $data['last_name'] = $data['lastName'];
            unset($data['lastName']);
        }

        $user->update($data);

        return $this->success($user->fresh()->toArray(), 'User updated successfully');
    }

    public function destroy($id)
    {
        $companyId = $this->getCompanyId();
        $user = User::where('company_id', $companyId)->findOrFail($id);

        $user->delete();

        return $this->success(null, 'User deleted successfully');
    }
}

