<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AgentTransactionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\CarController;
use App\Http\Controllers\Api\CarsTableController;
use App\Http\Controllers\Api\ContainerOpenerController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerLookupController;
use App\Http\Controllers\Api\CustomerPaymentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ServiceProviderController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierPaymentController;
use App\Http\Controllers\Api\TreasuryController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Full route map for the car import/sales management system. Every
| protected route sits behind 'auth:sanctum'; per-record and per-field
| authorization is then enforced inside each Controller via its matching
| Policy (see app/Providers/AuthServiceProvider.php for the full mapping).
|
*/

Route::prefix('auth')->group(function () {
    // Public endpoints (throttled to slow down brute-force attempts).
    // register() always creates a "customer" account — staff accounts
    // (admin/agent/super-admin) are only ever created via POST /users.
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'staff_only'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::match(['post', 'put'], 'update-password', [AuthController::class, 'updatePassword']);
        Route::match(['post', 'put'], 'update-profile', [AuthController::class, 'updateProfile']);
    });
});

// Sanity-check route to confirm auth + role/permission data is wired correctly.
Route::middleware(['auth:sanctum', 'staff_only'])->get('/ping', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'message' => 'pong',
        'user_id' => $request->user()->id,
        'roles' => $request->user()->getRoleNames(),
    ]);
});

// ------------------------------------------------------------------
// Public lookup — NO auth token required.
// A customer can check their order status using their passport number.
// Throttled at 10 req/min per IP to slow down enumeration attacks.
// ------------------------------------------------------------------
Route::middleware('throttle:lookup')->group(function () {
    Route::get('lookup/customer/{passport_no}', [CustomerLookupController::class, 'show']);
    Route::get('lookup/customer',               [CustomerLookupController::class, 'show']);
});

// Public catalogue - NO auth token required.
Route::get('cars/available', [CarController::class, 'available']);

Route::middleware(['auth:sanctum', 'staff_only'])->group(function () {

    // ------------------------------------------------------------------
    // أولاً: الموردين
    // ------------------------------------------------------------------
    Route::apiResource('suppliers', SupplierController::class);

    Route::apiResource('supplier-payments', SupplierPaymentController::class)
        ->parameters(['supplier-payments' => 'supplier_payment']);

    Route::post('batches/import', [BatchController::class, 'import']);
    Route::apiResource('batches', BatchController::class);

    // ------------------------------------------------------------------
    // ثانيًا: السيارات
    // ------------------------------------------------------------------
    // جدول السيارات المسطّح للتصدير - يجب تسجيله قبل apiResource('cars')
    // كي لا يلتقط route model binding كلمة "table" كقيمة لـ {car}.
    Route::get('cars/table', [CarsTableController::class, 'index']);
    Route::get('cars/table/export', [CarsTableController::class, 'export']);

    Route::apiResource('cars', CarController::class);
    Route::get('cars/{car}/expenses', [CarController::class, 'expenses']);
    Route::get('cars/{car}/ownership-history', [CarController::class, 'ownershipHistory']);
    Route::post('cars/{car}/expenses', [CarController::class, 'storeExpense']);

    // وثائق السيارة (متداخلة، تعتمد على CarPolicy - راجع DocumentController)
    Route::get('cars/{car}/documents', [DocumentController::class, 'index']);
    Route::post('cars/{car}/documents', [DocumentController::class, 'store']);
    Route::delete('cars/{car}/documents/{document}', [DocumentController::class, 'destroy']);

    Route::apiResource('container-openers', ContainerOpenerController::class)
        ->parameters(['container-openers' => 'container_opener']);

    Route::apiResource('service-providers', ServiceProviderController::class)
        ->parameters(['service-providers' => 'service_provider']);

    // ------------------------------------------------------------------
    // ثالثًا: العملاء
    // ------------------------------------------------------------------
    Route::apiResource('customers', CustomerController::class);

    // Customer profile documents (multiple file uploads)
    Route::get('customers/{customer}/documents', [CustomerController::class, 'documents']);
    Route::post('customers/{customer}/documents', [CustomerController::class, 'uploadDocuments']);
    Route::delete('customers/{customer}/documents/{document}', [CustomerController::class, 'deleteDocument']);

    // ------------------------------------------------------------------
    // رابعًا: الوكلاء
    // ------------------------------------------------------------------
    Route::apiResource('agents', AgentController::class);
    Route::get('agents/{agent}/customers', [AgentController::class, 'customers']);
    Route::get('agents/{agent}/statement', [AgentController::class, 'statement']);

    // ------------------------------------------------------------------
    // خامسًا: الطلبات
    // ------------------------------------------------------------------
    Route::apiResource('orders', OrderController::class);
    // Dedicated status-transition endpoint, separate from the general
    // update() — enforces the strict forward-only workflow (see
    // UpdateOrderStatusRequest), which a generic PATCH must not bypass.
    Route::patch('orders/{order}/status', [OrderController::class, 'changeStatus']);

    // ------------------------------------------------------------------
    // سادسًا: الدفعات المالية
    // ------------------------------------------------------------------
    Route::apiResource('customer-payments', CustomerPaymentController::class)
        ->parameters(['customer-payments' => 'customer_payment'])
        ->except(['update']); // financial records are corrected via void+recreate, not PUT/PATCH replace
    Route::post('customer-payments/remit', [CustomerPaymentController::class, 'remit']);
    Route::post('customer-payments/{customer_payment}/transfer-to-treasury', [CustomerPaymentController::class, 'transferToTreasury']);

    Route::apiResource('agent-transactions', AgentTransactionController::class)
        ->parameters(['agent-transactions' => 'agent_transaction'])
        ->except(['update']);
    Route::post('agent-transactions/{agent_transaction}/approve-remittance', [AgentTransactionController::class, 'approveRemittance']);

    // ------------------------------------------------------------------
    // سابعًا: الفواتير والمصاريف والمستندات
    // ------------------------------------------------------------------
    Route::apiResource('invoices', InvoiceController::class)->except(['update']);
    Route::post('invoices/{invoice}/refresh', [InvoiceController::class, 'refresh']);

    Route::apiResource('expenses', ExpenseController::class);

    // ------------------------------------------------------------------
    // ثامنًا: لوحة الإحصائيات
    // ------------------------------------------------------------------
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('tresury/summary', [TreasuryController::class, 'index']);
    Route::post('treasury/deposit', [TreasuryController::class, 'deposit']);

    // ------------------------------------------------------------------
    // عاشرًا: إدارة المستخدمين والصلاحيات
    // ------------------------------------------------------------------
    Route::apiResource('users', UserController::class);
    Route::patch('users/{user}/role', [UserController::class, 'changeRole']);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);

    // إعدادات النظام العامة
    Route::apiResource('settings', SettingController::class);
});
