<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsSet
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip tenant check for auth routes (public routes)
        $path = $request->path();
        if (str_starts_with($path, 'api/auth')) {
            return $next($request);
        }

        // Get company_id from header, query, or user session
        $companyId = $request->header('X-Company-Id') 
            ?? $request->query('company_id') 
            ?? auth()->user()?->company_id;

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        // Set company context for the request
        if ($companyId) {
            $request->merge(['company_id' => $companyId]);
            app()->instance('company_id', $companyId);
        }

        return $next($request);
    }
}

