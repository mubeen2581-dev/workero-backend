<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\JobController;

Route::get('/', function () {
    return response()->json([
        'message' => 'Workero API',
        'version' => '1.0.0',
    ]);
});

// Forward /auth/* requests to /api/auth/* for CORS compatibility
// This ensures CORS headers are sent even when frontend calls /auth/login instead of /api/auth/login
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Forward API routes without /api prefix (for frontend compatibility)
// These routes require JWT authentication
Route::middleware(['auth.jwt'])->group(function () {
    
    // Inventory routes
    Route::prefix('inventory')->middleware('role:admin,manager,warehouse')->group(function () {
        Route::get('/items', [InventoryController::class, 'items']);
        Route::post('/items', [InventoryController::class, 'store']);
        Route::get('/items/{id}', [InventoryController::class, 'show']);
        Route::put('/items/{id}', [InventoryController::class, 'update']);
        Route::delete('/items/{id}', [InventoryController::class, 'destroy']);
        Route::post('/items/{id}/adjust', [InventoryController::class, 'adjustStock']);
        Route::get('/movements', [InventoryController::class, 'movements']);
        Route::get('/stock', [InventoryController::class, 'stock']);
        Route::get('/alerts/low-stock', [InventoryController::class, 'lowStockAlerts']);
        Route::get('/transfers', [InventoryController::class, 'transfers']);
        Route::post('/transfer', [InventoryController::class, 'transfer']);
        Route::post('/issue-to-job', [InventoryController::class, 'issueToJob']);
        Route::post('/return-from-job/{jobMaterialId}', [InventoryController::class, 'returnFromJob']);
        Route::get('/job/{jobId}/materials', [InventoryController::class, 'getJobMaterials']);
    });
    
    // Warehouse routes
    Route::prefix('warehouses')->middleware('role:admin,manager,warehouse')->group(function () {
        Route::get('/', [WarehouseController::class, 'index']);
        Route::post('/', [WarehouseController::class, 'store']);
        Route::get('/{id}', [WarehouseController::class, 'show']);
        Route::put('/{id}', [WarehouseController::class, 'update']);
        Route::delete('/{id}', [WarehouseController::class, 'destroy']);
        Route::get('/{id}/stock', [WarehouseController::class, 'stock']);
    });
    
    // Supplier routes
    Route::prefix('suppliers')->middleware('role:admin,manager,warehouse')->group(function () {
        Route::get('/', [SupplierController::class, 'index']);
        Route::post('/', [SupplierController::class, 'store']);
        Route::get('/{id}', [SupplierController::class, 'show']);
        Route::put('/{id}', [SupplierController::class, 'update']);
        Route::delete('/{id}', [SupplierController::class, 'destroy']);
    });
    
    // Lead routes (Admin/Manager/Dispatcher can manage CRM)
    Route::prefix('leads')->middleware('role:admin,manager,dispatcher')->group(function () {
        Route::get('/', [LeadController::class, 'index']);
        Route::post('/', [LeadController::class, 'store']);
        Route::get('/workloads', [LeadController::class, 'workloads']); // Must be before /{id} routes
        Route::post('/distribute', [LeadController::class, 'distribute']); // Must be before /{id} routes
        Route::get('/{id}', [LeadController::class, 'show']);
        Route::put('/{id}', [LeadController::class, 'update']);
        Route::delete('/{id}', [LeadController::class, 'destroy']);
        Route::get('/{id}/activities', [LeadController::class, 'activities']);
        Route::post('/{id}/status', [LeadController::class, 'updateStatus']);
        Route::post('/{id}/assign', [LeadController::class, 'assign']);
    });
    
    // Quote routes (Admin/Manager/Dispatcher/Technician can view, others need specific permissions)
    Route::prefix('quotes')->group(function () {
        Route::get('/', [QuoteController::class, 'index'])->middleware('role:admin,manager,dispatcher,technician');
        Route::post('/', [QuoteController::class, 'store'])->middleware('role:admin,manager,dispatcher,technician');
        Route::get('/{id}', [QuoteController::class, 'show'])->middleware('role:admin,manager,dispatcher,technician,client');
        Route::put('/{id}', [QuoteController::class, 'update'])->middleware('role:admin,manager,dispatcher,technician');
        Route::delete('/{id}', [QuoteController::class, 'destroy'])->middleware('role:admin,manager');
        Route::post('/{id}/send', [QuoteController::class, 'send'])->middleware('role:admin,manager,dispatcher');
        Route::post('/{id}/accept', [QuoteController::class, 'accept'])->middleware('role:admin,manager,client');
        Route::post('/{id}/reject', [QuoteController::class, 'reject'])->middleware('role:admin,manager,client');
        Route::post('/{id}/sign', [QuoteController::class, 'sign']);
        Route::post('/{id}/decline', [QuoteController::class, 'decline']);
        Route::post('/{id}/convert-to-job', [QuoteController::class, 'convertToJob'])->middleware('role:admin,manager,dispatcher');
        Route::get('/{id}/pdf', [QuoteController::class, 'downloadPdf'])->middleware('role:admin,manager,dispatcher,technician,client');
        Route::get('/{id}/pdf/stream', [QuoteController::class, 'streamPdf'])->middleware('role:admin,manager,dispatcher,technician,client');
    });
    
    // Job routes (Admin/Manager can do everything, Technician can view/update own, Dispatcher can view)
    Route::prefix('jobs')->group(function () {
        Route::get('/', [JobController::class, 'index'])->middleware('role:admin,manager,dispatcher,technician');
        Route::post('/', [JobController::class, 'store'])->middleware('role:admin,manager');
        Route::get('/{id}', [JobController::class, 'show'])->middleware('role:admin,manager,dispatcher,technician,client');
        Route::put('/{id}', [JobController::class, 'update'])->middleware('role:admin,manager,technician');
        Route::delete('/{id}', [JobController::class, 'destroy'])->middleware('role:admin,manager');
        Route::post('/{id}/assign', [JobController::class, 'assign'])->middleware('role:admin,manager');
        Route::post('/{id}/complete', [JobController::class, 'complete'])->middleware('role:admin,manager,technician');
        Route::get('/{id}/activities', [JobController::class, 'activities'])->middleware('role:admin,manager,dispatcher,technician');
    });
});

// Serve storage files (for avatars, etc.)
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath)) {
        abort(404);
    }
    
    $file = file_get_contents($filePath);
    $type = mime_content_type($filePath);
    
    return response($file, 200)->header('Content-Type', $type);
})->where('path', '.*');

