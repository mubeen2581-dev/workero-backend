<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Company;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Register a new user (Public endpoint).
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'role' => 'nullable|in:admin,manager,technician,dispatcher',
            'companyId' => 'nullable|uuid|exists:companies,id',
            'companyName' => 'nullable|string|max:255',
            'skills' => 'nullable|array',
            'team' => 'nullable|string',
            'region' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Determine company
            $companyId = $request->input('companyId');
            
            if ($companyId) {
                // User is joining an existing company (invitation scenario)
                $company = Company::findOrFail($companyId);
                $role = $request->input('role', 'technician'); // Default to technician for invited users
            } else {
                // Create new company for the user (new business owner)
                $companyName = $request->input('companyName', $request->input('firstName') . ' ' . $request->input('lastName') . ' Company');
                
                $company = Company::create([
                    'id' => Str::uuid(),
                    'name' => $companyName,
                    'email' => $request->input('email'),
                    'subscription_tier' => 'free',
                    'is_active' => true,
                ]);
                
                $companyId = $company->id;
                $role = 'admin'; // First user is always admin
            }

            // Create user
            $user = User::create([
                'id' => Str::uuid(),
                'company_id' => $companyId,
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'first_name' => $request->input('firstName'),
                'last_name' => $request->input('lastName'),
                'role' => $role,
                'skills' => $request->input('skills', []),
                'team' => $request->input('team'),
                'region' => $request->input('region'),
                'is_active' => true,
            ]);

            // Generate JWT token for the newly registered user
            $token = JWTAuth::fromUser($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'firstName' => $user->first_name,
                        'lastName' => $user->last_name,
                        'role' => $user->role,
                        'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                        'skills' => $user->skills,
                        'team' => $user->team,
                        'region' => $user->region,
                        'isActive' => $user->is_active,
                        'companyId' => $user->company_id,
                        'createdAt' => $user->created_at->toISOString(),
                    ],
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                        'email' => $company->email,
                    ],
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => JWTAuth::factory()->getTTL() * 60,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('User registration failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Get a JWT via given credentials.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            $user = auth()->user();

            // Check if user is active
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is inactive',
                ], 403);
            }

            // Update last login
            $user->update(['last_login_at' => now()]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'firstName' => $user->first_name,
                        'lastName' => $user->last_name,
                        'role' => $user->role,
                        'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                        'skills' => $user->skills,
                        'team' => $user->team,
                        'region' => $user->region,
                        'isActive' => $user->is_active,
                        'createdAt' => $user->created_at->toISOString(),
                    ],
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => JWTAuth::factory()->getTTL() * 60,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not create token',
            ], 500);
        }
    }

    /**
     * Get the authenticated user.
     */
    public function me()
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'role' => $user->role,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'skills' => $user->skills,
                'team' => $user->team,
                'region' => $user->region,
                'isActive' => $user->is_active,
                'createdAt' => $user->created_at->toISOString(),
                'lastLoginAt' => $user->last_login_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Refresh a token.
     */
    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => JWTAuth::factory()->getTTL() * 60,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not refresh token',
            ], 401);
        }
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout',
            ], 500);
        }
    }

    /**
     * Forgot password.
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = $request->email;
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Generate password reset token
            $token = Str::random(64);

            // Store token in database (upsert to handle existing records)
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            // Generate reset URL (you can customize this based on your frontend URL)
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            $resetUrl = "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($email);

            // Send password reset email
            try {
                Mail::to($user->email)->send(new PasswordResetMail($user, $resetUrl));

                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email',
                ]);
            } catch (\Exception $e) {
                \Log::error('Password reset email failed: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Still return success to user (security best practice - don't reveal if email exists)
                // But log the error for debugging
                return response()->json([
                    'success' => true,
                    'message' => 'If an account with that email exists, a password reset link has been sent.',
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Forgot password failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request. Please try again later.',
            ], 500);
        }
    }

    /**
     * Reset password.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->email;
        $token = $request->token;
        $password = $request->password;

        // Find the password reset record
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token',
            ], 400);
        }

        // Check if token is valid (within 60 minutes)
        $tokenAge = now()->diffInMinutes($passwordReset->created_at);
        if ($tokenAge > 60) {
            // Delete expired token
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.',
            ], 400);
        }

        // Verify the token
        if (!Hash::check($token, $passwordReset->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token',
            ], 400);
        }

        // Update user password
        $user = User::where('email', $email)->first();
        $user->password = Hash::make($password);
        $user->save();

        // Delete the used token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        
        // Log all request data
        \Log::info('Profile update request received', [
            'has_avatar_file' => $request->hasFile('avatar'),
            'all_files' => $request->allFiles(),
            'all_input' => $request->except(['avatar']),
        ]);

        $validator = Validator::make($request->all(), [
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'skills' => 'nullable|array',
            'team' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = [];

        // Handle file upload - check both hasFile and file() method
        $avatarFile = $request->file('avatar');
        if ($request->hasFile('avatar') || $avatarFile) {
            $file = $avatarFile ?: $request->file('avatar');
            
            \Log::info('Avatar file received', [
                'has_file' => $request->hasFile('avatar'),
                'file_exists' => $file !== null,
                'file_name' => $file ? $file->getClientOriginalName() : 'N/A',
                'file_size' => $file ? $file->getSize() : 0,
                'mime_type' => $file ? $file->getMimeType() : 'N/A',
            ]);
            
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
                \Log::info('Old avatar deleted', ['path' => $user->avatar]);
            }

            // Store new avatar
            $avatarPath = $file->store('avatars', 'public');
            $data['avatar'] = $avatarPath;
            
            \Log::info('Avatar stored', [
                'path' => $avatarPath,
                'full_path' => storage_path('app/public/' . $avatarPath),
                'exists' => Storage::disk('public')->exists($avatarPath),
            ]);
        } else {
            \Log::warning('No avatar file in request', [
                'has_file' => $request->hasFile('avatar'),
                'file_method' => $request->file('avatar') !== null,
                'all_files' => array_keys($request->allFiles()),
            ]);
        }

        // Handle other fields
        if ($request->has('firstName')) {
            $data['first_name'] = $request->input('firstName');
        }
        if ($request->has('lastName')) {
            $data['last_name'] = $request->input('lastName');
        }
        if ($request->has('email')) {
            $data['email'] = $request->input('email');
        }
        if ($request->has('skills')) {
            $data['skills'] = $request->input('skills');
        }
        if ($request->has('team')) {
            $data['team'] = $request->input('team');
        }
        if ($request->has('region')) {
            $data['region'] = $request->input('region');
        }

        \Log::info('Data to update:', ['data' => $data]);
        
        $user->update($data);
        
        // Refresh user to get updated data
        $user->refresh();
        
        // Log for debugging
        \Log::info('User avatar after update:', [
            'user_id' => $user->id,
            'avatar_in_db' => $user->avatar,
            'data_avatar' => $data['avatar'] ?? 'not set',
        ]);
        
        // Double-check: if avatar was in data but not in user, try to set it directly
        if (isset($data['avatar']) && !$user->avatar) {
            \Log::warning('Avatar path lost during update, attempting direct save');
            $user->avatar = $data['avatar'];
            $user->save();
            $user->refresh();
            \Log::info('Avatar after direct save:', ['avatar' => $user->avatar]);
        }

        // Generate avatar URL
        $avatarUrl = null;
        if ($user->avatar) {
            // Use Storage::url() which returns the correct URL
            $avatarUrl = Storage::disk('public')->url($user->avatar);
            \Log::info('Generated avatar URL:', [
                'avatar_path' => $user->avatar,
                'url' => $avatarUrl,
                'app_url' => config('app.url')
            ]);
        } else {
            \Log::warning('User avatar is null after update', ['user_id' => $user->id, 'data' => $data]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'role' => $user->role,
                'avatar' => $avatarUrl,
                'skills' => $user->skills,
                'team' => $user->team,
                'region' => $user->region,
                'isActive' => $user->is_active,
                'createdAt' => $user->created_at->toISOString(),
                'lastLoginAt' => $user->last_login_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();

        // Verify current password
        if (!Hash::check($request->input('currentPassword'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        // Update password
        $user->password = Hash::make($request->input('newPassword'));
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Remove the authenticated user's avatar.
     */
    public function removeAvatar()
    {
        $user = auth()->user();
        
        // Delete the old avatar file if it exists
        if ($user->avatar) {
            $avatarPath = $user->avatar;
            
            // Delete from storage
            if (Storage::disk('public')->exists($avatarPath)) {
                Storage::disk('public')->delete($avatarPath);
            }
            
            // Clear avatar from database
            $user->avatar = null;
            $user->save();
        }
        
        // Prepare user data for response
        $userData = [
            'id' => $user->id,
            'email' => $user->email,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'role' => $user->role,
            'avatar' => null,
            'skills' => $user->skills,
            'team' => $user->team,
            'region' => $user->region,
            'isActive' => $user->is_active,
            'createdAt' => $user->created_at->toISOString(),
            'lastLoginAt' => $user->last_login_at?->toISOString(),
        ];
        
        return response()->json([
            'success' => true,
            'message' => 'Avatar removed successfully',
            'data' => $userData,
        ]);
    }
}

