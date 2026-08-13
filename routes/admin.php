<?php

use App\Http\Controllers\Api\V1\Admin\BillingMetricsController;
use App\Http\Controllers\Api\V1\Admin\DownloadAdminController;
use App\Http\Controllers\Api\V1\Admin\LicenseAdminController;
use App\Http\Controllers\Api\V1\Admin\ProductAssignmentController;
use App\Http\Controllers\Api\V1\Admin\ReleaseController;
use App\Http\Controllers\Api\V1\AdminDashboardController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Loaded inside: prefix('admin') + middleware('admin') + middleware('auth:sanctum')
| All routes here are: /api/admin/...
|
*/

// Dashboard
Route::get('/dashboard', [AdminDashboardController::class, 'overview']);
Route::get('/dashboard/recent-orders', [AdminDashboardController::class, 'recentOrders']);
Route::get('/dashboard/recent-leads', [AdminDashboardController::class, 'recentLeads']);
Route::get('/dashboard/recent-tickets', [AdminDashboardController::class, 'recentTickets']);

// Billing Metrics
Route::get('/billing/metrics', [BillingMetricsController::class, 'index']);

// Product Management
Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);

// Product Releases
Route::get('products/{product}/releases', [ReleaseController::class, 'index']);
Route::post('products/{product}/releases', [ReleaseController::class, 'store']);
Route::get('products/{product}/releases/{release}', [ReleaseController::class, 'show']);
Route::put('products/{product}/releases/{release}', [ReleaseController::class, 'update']);
Route::delete('products/{product}/releases/{release}', [ReleaseController::class, 'destroy']);
Route::post('products/{product}/releases/{release}/publish', [ReleaseController::class, 'publish']);
Route::post('products/{product}/releases/{release}/deprecate', [ReleaseController::class, 'deprecate']);
Route::post('products/{product}/releases/{release}/rollback', [ReleaseController::class, 'rollback']);

// Downloads Management
Route::get('downloads', [DownloadAdminController::class, 'index']);
Route::get('downloads/stats', [DownloadAdminController::class, 'stats']);
Route::get('download-logs', [DownloadAdminController::class, 'logs']);

// Product Staff Assignments
Route::get('products/{product}/staff', [ProductAssignmentController::class, 'index']);
Route::post('products/{product}/staff', [ProductAssignmentController::class, 'store']);
Route::put('products/{product}/staff/{assignment}', [ProductAssignmentController::class, 'update']);
Route::delete('products/{product}/staff/{assignment}', [ProductAssignmentController::class, 'destroy']);

// User Product Assignments
Route::get('users/{user}/products', [ProductAssignmentController::class, 'userProducts']);

// License Management
Route::get('licenses', [LicenseAdminController::class, 'index']);
Route::get('licenses/stale-activations', [LicenseAdminController::class, 'staleActivations']);
Route::get('licenses/{license}', [LicenseAdminController::class, 'show']);
Route::get('licenses/{license}/activations', [LicenseAdminController::class, 'activations']);
Route::post('licenses/{license}/suspend', [LicenseAdminController::class, 'suspend']);
Route::post('licenses/{license}/unsuspend', [LicenseAdminController::class, 'unsuspend']);
Route::post('licenses/{license}/revoke', [LicenseAdminController::class, 'revoke']);
Route::post('licenses/{license}/renew', [LicenseAdminController::class, 'renew']);
Route::put('licenses/{license}/upgrade', [LicenseAdminController::class, 'upgrade']);
Route::put('licenses/{license}/transfer', [LicenseAdminController::class, 'transfer']);
