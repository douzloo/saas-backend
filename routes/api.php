<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CmsController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DownloadController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\LeadNoteController;
use App\Http\Controllers\Api\V1\LeadStageController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\Portal\PortalController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsStaff;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rate Limiting
|--------------------------------------------------------------------------
*/

RateLimiter::for('global', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
RateLimiter::for('dashboard', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
RateLimiter::for('license-verify', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

/*
|--------------------------------------------------------------------------
| Middleware Aliases
|--------------------------------------------------------------------------
*/

Route::aliasMiddleware('admin', EnsureUserIsAdmin::class);
Route::aliasMiddleware('staff', EnsureUserIsStaff::class);

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/categories', [ProductController::class, 'categories']);
Route::get('/products/{product:slug}', [ProductController::class, 'show']);
Route::get('/products/{product:slug}/faqs', [ProductController::class, 'faqs']);

Route::get('/products/{product:slug}/releases', [ProductController::class, 'releases']);
Route::get('/products/{product:slug}/releases/latest', [ProductController::class, 'latestRelease']);

Route::get('/downloads', [DownloadController::class, 'index']);
Route::get('/downloads/latest', [DownloadController::class, 'latest']);
Route::get('/downloads/product/{product:slug}', [DownloadController::class, 'byProduct']);
Route::get('/downloads/{download}', [DownloadController::class, 'download']);

Route::get('/settings', [CmsController::class, 'settings']);
Route::get('/pages', [CmsController::class, 'pages']);
Route::get('/pages/{slug}', [CmsController::class, 'page']);

/*
|--------------------------------------------------------------------------
| Desktop License API (unauthenticated — for apps)
|--------------------------------------------------------------------------
*/

Route::prefix('license')->group(function () {
    Route::post('/activate', [LicenseController::class, 'desktopActivate']);
    Route::post('/deactivate', [LicenseController::class, 'desktopDeactivate']);
    Route::post('/verify', [LicenseController::class, 'verify'])->middleware('throttle:license-verify');
    Route::post('/heartbeat', [LicenseController::class, 'heartbeat']);
});

/*
|--------------------------------------------------------------------------
| Contact
|--------------------------------------------------------------------------
*/

Route::post('/contact', [ContactController::class, 'submit'])->middleware('throttle:contact');

/*
|--------------------------------------------------------------------------
| Public Auth
|--------------------------------------------------------------------------
*/

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/auth/user', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{order}/pay', [OrderController::class, 'pay']);
    Route::post('/orders/{order}/payments/{payment}/verify', [OrderController::class, 'verifyPayment']);
    Route::post('/orders/{order}/coupon', [OrderController::class, 'applyCoupon']);
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice']);

    // Billing — Invoices
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);
    Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay']);
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);

    // Billing — Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::post('/tickets/{ticket}/messages', [TicketController::class, 'reply']);
    Route::get('/tickets/{ticket}/messages', [TicketController::class, 'messages']);
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign']);
    Route::put('/tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
    Route::get('/ticket-attachments/{attachment}/download', [TicketController::class, 'downloadAttachment']);

    // Portal — Customer-facing dashboard & documents
    Route::prefix('portal')->group(function () {
        Route::get('/dashboard', [PortalController::class, 'dashboard']);
        Route::get('/invoices', [PortalController::class, 'invoices']);
        Route::get('/invoices/{invoice}', [PortalController::class, 'invoice']);
        Route::get('/downloads', [PortalController::class, 'downloads']);

        // Portal — Customer-facing license management
        Route::get('/licenses', [LicenseController::class, 'index']);
        Route::get('/licenses/{key}', [LicenseController::class, 'show']);
        Route::get('/licenses/{key}/activations', [LicenseController::class, 'activations']);
        Route::post('/licenses/activate', [LicenseController::class, 'activate']);
        Route::post('/licenses/deactivate', [LicenseController::class, 'deactivate']);
        Route::post('/licenses/heartbeat', [LicenseController::class, 'portalHeartbeat']);
    });

    // CRM (staff with product access)
    Route::prefix('crm')->middleware('staff')->group(function () {
        Route::get('/leads', [LeadController::class, 'index']);
        Route::post('/leads', [LeadController::class, 'store']);
        Route::get('/leads/stats', [LeadController::class, 'stats']);
        Route::get('/leads/pipeline', [LeadController::class, 'pipeline']);
        Route::post('/leads/bulk-assign', [LeadController::class, 'bulkAssign']);
        Route::get('/leads/reminders', [LeadController::class, 'reminders']);

        // Lead stages
        Route::get('/lead-stages', [LeadStageController::class, 'index']);
        Route::post('/lead-stages', [LeadStageController::class, 'store']);
        Route::put('/lead-stages/{stage}', [LeadStageController::class, 'update']);
        Route::delete('/lead-stages/{stage}', [LeadStageController::class, 'destroy']);

        Route::get('/leads/{lead}', [LeadController::class, 'show']);
        Route::put('/leads/{lead}', [LeadController::class, 'update']);
        Route::put('/leads/{lead}/status', [LeadController::class, 'updateStatus']);
        Route::post('/leads/{lead}/assign', [LeadController::class, 'assign']);
        Route::post('/leads/{lead}/convert', [LeadController::class, 'convert']);
        Route::get('/leads/{lead}/activities', [LeadController::class, 'activities']);
        Route::post('/leads/{lead}/activities', [LeadController::class, 'addActivity']);

        // Lead notes
        Route::get('/leads/{lead}/notes', [LeadNoteController::class, 'index']);
        Route::post('/leads/{lead}/notes', [LeadNoteController::class, 'store']);
        Route::put('/lead-notes/{note}', [LeadNoteController::class, 'update']);
        Route::delete('/lead-notes/{note}', [LeadNoteController::class, 'destroy']);

        // Lead reminders
        Route::post('/leads/{lead}/reminders', [LeadController::class, 'createReminder']);
        Route::put('/reminders/{activity}/complete', [LeadController::class, 'completeReminder']);
    });

    // Admin
    Route::prefix('admin')->middleware('admin')->group(function () {
        require base_path('routes/admin.php');
    });
});
