<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\VanStockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// API Info endpoint
Route::get('/', function () {
    return response()->json([
        'message' => 'Workero API',
        'version' => '1.0.0',
        'status' => 'active',
        'endpoints' => [
            'auth' => [
                'public' => [
                    'register' => 'POST /api/auth/register',
                    'login' => 'POST /api/auth/login',
                    'forgot-password' => 'POST /api/auth/forgot-password',
                    'reset-password' => 'POST /api/auth/reset-password',
                ],
                'protected' => [
                    'logout' => 'POST /api/auth/logout',
                    'refresh' => 'POST /api/auth/refresh',
                    'me' => 'GET /api/auth/me',
                ],
            ],
            'users' => '/api/users',
            'clients' => '/api/clients',
            'leads' => '/api/leads',
            'quotes' => '/api/quotes',
            'jobs' => '/api/jobs',
            'invoices' => '/api/invoices',
            'payments' => '/api/payments',
            'schedule' => '/api/schedule',
            'inventory' => '/api/inventory',
            'messages' => '/api/messages',
            'compliance' => '/api/compliance',
            'analytics' => '/api/analytics',
        ],
    ]);
});

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Protected routes
Route::middleware(['auth.jwt'])->group(function () {
    
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::match(['put', 'post'], '/profile', [AuthController::class, 'updateProfile']);
        Route::delete('/profile/avatar', [AuthController::class, 'removeAvatar']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });

    // User routes (Admin/Manager only)
    Route::prefix('users')->middleware('role:admin,manager')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });

    // Client routes (Admin/Manager/Dispatcher can manage CRM)
    Route::prefix('clients')->middleware('role:admin,manager,dispatcher')->group(function () {
        Route::get('/', [ClientController::class, 'index']);
        Route::post('/', [ClientController::class, 'store']);
        Route::get('/{id}', [ClientController::class, 'show']);
        Route::put('/{id}', [ClientController::class, 'update']);
        Route::delete('/{id}', [ClientController::class, 'destroy']);
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
        Route::post('/{id}/sign', [QuoteController::class, 'sign']);
        Route::post('/{id}/decline', [QuoteController::class, 'decline']);
        Route::post('/{id}/generate-contract', [QuoteController::class, 'generateContract'])->middleware('role:admin,manager,dispatcher');
        Route::get('/{id}/contract/pdf', [QuoteController::class, 'downloadContractPdf'])->middleware('role:admin,manager,dispatcher,technician,client');
        Route::get('/', [QuoteController::class, 'index'])->middleware('role:admin,manager,dispatcher,technician');
        Route::post('/', [QuoteController::class, 'store'])->middleware('role:admin,manager,dispatcher,technician');
        Route::get('/{id}', [QuoteController::class, 'show'])->middleware('role:admin,manager,dispatcher,technician,client');
        Route::put('/{id}', [QuoteController::class, 'update'])->middleware('role:admin,manager,dispatcher,technician');
        Route::delete('/{id}', [QuoteController::class, 'destroy'])->middleware('role:admin,manager');
        Route::post('/{id}/send', [QuoteController::class, 'send'])->middleware('role:admin,manager,dispatcher');
        Route::post('/{id}/accept', [QuoteController::class, 'accept'])->middleware('role:admin,manager,client');
        Route::post('/{id}/reject', [QuoteController::class, 'reject'])->middleware('role:admin,manager,client');
        Route::post('/{id}/convert-to-job', [QuoteController::class, 'convertToJob'])->middleware('role:admin,manager,dispatcher');
        Route::get('/{id}/pdf', [QuoteController::class, 'downloadPdf'])->middleware('role:admin,manager,dispatcher,technician,client');
        Route::get('/{id}/pdf/stream', [QuoteController::class, 'streamPdf'])->middleware('role:admin,manager,dispatcher,technician,client');
        
        // AI Quote Builder routes
        Route::prefix('ai')->group(function () {
            Route::post('/generate', [\App\Http\Controllers\AIQuoteController::class, 'generateSuggestions'])->middleware('role:admin,manager,dispatcher,technician');
            Route::get('/historical-pricing', [\App\Http\Controllers\AIQuoteController::class, 'getHistoricalPricing'])->middleware('role:admin,manager,dispatcher');
            Route::post('/material-recommendations', [\App\Http\Controllers\AIQuoteController::class, 'getMaterialRecommendations'])->middleware('role:admin,manager,dispatcher,technician');
            Route::post('/optimize-pricing', [\App\Http\Controllers\AIQuoteController::class, 'optimizePricing'])->middleware('role:admin,manager,dispatcher');
        });
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

    // Schedule routes (Admin/Manager only)
    Route::prefix('schedule')->middleware('role:admin,manager')->group(function () {
        Route::get('/events', [ScheduleController::class, 'events']);
        Route::get('/availability', [ScheduleController::class, 'availability']);
        Route::get('/conflicts', [ScheduleController::class, 'conflicts']);
    });

    // Invoice routes (Admin/Manager/Dispatcher can manage, Client can view own and pay)
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->middleware('role:admin,manager,dispatcher');
        Route::post('/', [InvoiceController::class, 'store'])->middleware('role:admin,manager,dispatcher');
        Route::post('/generate-from-job/{jobId}', [InvoiceController::class, 'generateFromJob'])->middleware('role:admin,manager,dispatcher');
        Route::get('/{id}', [InvoiceController::class, 'show'])->middleware('role:admin,manager,dispatcher,client');
        Route::put('/{id}', [InvoiceController::class, 'update'])->middleware('role:admin,manager,dispatcher');
        Route::delete('/{id}', [InvoiceController::class, 'destroy'])->middleware('role:admin,manager');
        Route::post('/{id}/send', [InvoiceController::class, 'send'])->middleware('role:admin,manager,dispatcher');
        Route::post('/{id}/pay', [InvoiceController::class, 'pay'])->middleware('role:admin,manager,client');
        Route::get('/{id}/pdf', [InvoiceController::class, 'downloadPdf'])->middleware('role:admin,manager,dispatcher,client');
        Route::get('/{id}/pdf/stream', [InvoiceController::class, 'streamPdf'])->middleware('role:admin,manager,dispatcher,client');
    });

    // Payment routes (Admin/Manager/Dispatcher can view, Client can make payments)
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->middleware('role:admin,manager,dispatcher');
        Route::post('/', [PaymentController::class, 'store'])->middleware('role:admin,manager,client');
        Route::get('/{id}', [PaymentController::class, 'show'])->middleware('role:admin,manager,dispatcher,client');
        Route::get('/methods', [PaymentController::class, 'methods'])->middleware('role:admin,manager,dispatcher,client');
        Route::post('/xe-pay/link', [PaymentController::class, 'createXEPayLink'])->middleware('role:admin,manager,client');
        Route::post('/xe-pay/status', [PaymentController::class, 'xePayStatus'])->middleware('role:admin,manager,client');
    });

    // Inventory routes (Warehouse role or Admin/Manager for view)
    Route::prefix('inventory')->group(function () {
        // Items CRUD
        Route::get('/items', [InventoryController::class, 'items'])->middleware('role:admin,manager,warehouse');
        Route::post('/items', [InventoryController::class, 'store'])->middleware('role:admin,manager,warehouse');
        Route::get('/items/{id}', [InventoryController::class, 'show'])->middleware('role:admin,manager,warehouse');
        Route::put('/items/{id}', [InventoryController::class, 'update'])->middleware('role:admin,manager,warehouse');
        Route::delete('/items/{id}', [InventoryController::class, 'destroy'])->middleware('role:admin,manager,warehouse');
        Route::post('/items/{id}/adjust', [InventoryController::class, 'adjustStock'])->middleware('role:admin,manager,warehouse');
        
        // Stock movements
        Route::get('/movements', [InventoryController::class, 'movements'])->middleware('role:admin,manager,warehouse');
        
        // Stock queries
        Route::get('/stock', [InventoryController::class, 'stock'])->middleware('role:admin,manager,warehouse');
        Route::get('/alerts/low-stock', [InventoryController::class, 'lowStockAlerts'])->middleware('role:admin,manager,warehouse');
        
        // Transfers
        Route::get('/transfers', [InventoryController::class, 'transfers'])->middleware('role:admin,manager,warehouse');
        Route::post('/transfer', [InventoryController::class, 'transfer'])->middleware('role:admin,manager,warehouse');
        
        // Job materials
        Route::post('/issue-to-job', [InventoryController::class, 'issueToJob'])->middleware('role:admin,manager,warehouse');
        Route::post('/return-from-job/{jobMaterialId}', [InventoryController::class, 'returnFromJob'])->middleware('role:admin,manager,warehouse');
        Route::get('/job/{jobId}/materials', [InventoryController::class, 'getJobMaterials'])->middleware('role:admin,manager,warehouse,technician');
    });

    // Warehouse routes (Admin/Manager/Warehouse)
    Route::prefix('warehouses')->middleware('role:admin,manager,warehouse')->group(function () {
        Route::get('/', [WarehouseController::class, 'index']);
        Route::post('/', [WarehouseController::class, 'store']);
        Route::get('/{id}', [WarehouseController::class, 'show']);
        Route::put('/{id}', [WarehouseController::class, 'update']);
        Route::delete('/{id}', [WarehouseController::class, 'destroy']);
        Route::get('/{id}/stock', [WarehouseController::class, 'stock']);
    });

    // Van Stock routes (Admin/Manager/Warehouse)
    Route::prefix('van-stock')->middleware('role:admin,manager,warehouse')->group(function () {
        Route::get('/', [VanStockController::class, 'index']);
        Route::get('/technician/{technicianId}', [VanStockController::class, 'getByTechnician']);
        Route::post('/assign', [VanStockController::class, 'assign']);
        Route::post('/{id}/return', [VanStockController::class, 'return']);
    });

    // Supplier routes (Admin/Manager/Warehouse)
    Route::prefix('suppliers')->middleware('role:admin,manager,warehouse')->group(function () {
        Route::get('/', [SupplierController::class, 'index']);
        Route::post('/', [SupplierController::class, 'store']);
        Route::get('/{id}', [SupplierController::class, 'show']);
        Route::put('/{id}', [SupplierController::class, 'update']);
        Route::delete('/{id}', [SupplierController::class, 'destroy']);
    });

    // Message routes (Admin/Manager/Dispatcher/Technician can communicate)
    Route::prefix('messages')->middleware('role:admin,manager,dispatcher,technician')->group(function () {
        Route::get('/', [MessageController::class, 'index']);
        Route::post('/send', [MessageController::class, 'send']);
        Route::get('/threads', [MessageController::class, 'threads']);
        Route::get('/templates', [MessageController::class, 'templates']);
    });

    // Compliance routes (Admin/Manager/Dispatcher only)
    Route::prefix('compliance')->middleware('role:admin,manager,dispatcher')->group(function () {
        Route::get('/documents', [ComplianceController::class, 'index']);
        Route::post('/documents/upload', [ComplianceController::class, 'upload']);
        Route::delete('/documents/{id}', [ComplianceController::class, 'destroy']);
    });

    // Analytics routes (Admin/Manager can view all, Dispatcher has limited access)
    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard', [AnalyticsController::class, 'dashboard'])->middleware('role:admin,manager,dispatcher');
        Route::get('/reports', [AnalyticsController::class, 'reports'])->middleware('role:admin,manager,dispatcher');
        Route::get('/kpis', [AnalyticsController::class, 'kpis'])->middleware('role:admin,manager');
    });
});

