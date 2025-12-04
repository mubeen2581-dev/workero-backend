<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Get company ID from request.
     */
    protected function getCompanyId()
    {
        $companyId = request()->header('X-Company-Id') 
            ?? request()->input('company_id') 
            ?? auth()->user()?->company_id;
        
        // Debug logging
        if (!$companyId && auth()->check()) {
            \Log::warning('Controller::getCompanyId - No company_id found', [
                'user_id' => auth()->id(),
                'user_email' => auth()->user()?->email,
                'user_company_id' => auth()->user()?->company_id,
                'header' => request()->header('X-Company-Id'),
                'input' => request()->input('company_id'),
            ]);
        }
        
        return $companyId;
    }

    /**
     * Return success response.
     */
    protected function success($data, $message = null, $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Return error response.
     */
    protected function error($message, $errors = null, $statusCode = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    /**
     * Return paginated response.
     */
    protected function paginated($data, $pagination, $message = null)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => $pagination,
            'message' => $message,
        ]);
    }
}

