<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['status' => 'ok']));
Route::get('/test', fn () => response()->json(['message' => 'test API']));

// Dashboard API routes
Route::prefix('dashboard')->group(function () {
    Route::get('/kpis', [\App\Http\Controllers\Api\DashboardController::class, 'getKPIs']);
    Route::get('/analytics', [\App\Http\Controllers\Api\DashboardController::class, 'getUsageAnalytics']);
    Route::get('/service-performance', [\App\Http\Controllers\Api\DashboardController::class, 'getServicePerformance']);
    Route::get('/office-activity', [\App\Http\Controllers\Api\DashboardController::class, 'getOfficeActivity']);
    Route::get('/recent-logs', [\App\Http\Controllers\Api\DashboardController::class, 'getRecentLogs']);
});

// Service Catalog API routes (Consumer side)
Route::prefix('catalog')->group(function () {
    Route::get('/filters', [\App\Http\Controllers\Api\ServiceCatalogController::class, 'getAvailableFilters']);
    Route::get('/services', [\App\Http\Controllers\Api\ServiceCatalogController::class, 'getServiceCatalog']);
    Route::get('/services/{id}', [\App\Http\Controllers\Api\ServiceCatalogController::class, 'getServiceDetails']);
    Route::post('/load-mock-services', [\App\Http\Controllers\Api\ServiceCatalogController::class, 'loadMockServices']);
});

// PHASE 1: Service Requests (Publisher side - HU001) is being left by another
Route::prefix('service-requests')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ServiceRequestController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\ServiceRequestController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Api\ServiceRequestController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\ServiceRequestController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\ServiceRequestController::class, 'destroy']);
});

// PHASE 2: Service Approval (Admin side - HU001_ADMIN)
Route::prefix('admin')->group(function () {
    Route::prefix('service-approvals')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ServiceApprovalController::class, 'index']);
        Route::get('/pending-count', [\App\Http\Controllers\Api\ServiceApprovalController::class, 'getPendingCount']);
        Route::get('/{id}', [\App\Http\Controllers\Api\ServiceApprovalController::class, 'show']);
        Route::post('/{id}/approve', [\App\Http\Controllers\Api\ServiceApprovalController::class, 'approve']);
        Route::post('/{id}/reject', [\App\Http\Controllers\Api\ServiceApprovalController::class, 'reject']);
        Route::post('/{id}/request-modifications', [\App\Http\Controllers\Api\ServiceApprovalController::class, 'requestModifications']);
    });
    // Rutas que espera el frontend de admin
    Route::get('/services/pending', [\App\Http\Controllers\Api\ServiceApprovalBySlugController::class, 'index']);
    Route::post('/services/approve/{slug_param}', [\App\Http\Controllers\Api\ServiceApprovalBySlugController::class, 'approve']);
    Route::post('/services/reject/{slug_param}', [\App\Http\Controllers\Api\ServiceApprovalBySlugController::class, 'reject']);
    Route::patch('/services/{slug_param}/endpoint', [\App\Http\Controllers\Api\ServiceApprovalBySlugController::class, 'updateEndpoint']);
});

// PHASE 3: Service Management and Publication (Publisher side)
Route::prefix('services')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ServiceRegistrationController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\ServiceRegistrationController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\ServiceRegistrationController::class, 'update']);
    Route::post('/{id}/publish', [\App\Http\Controllers\Api\ServiceRegistrationController::class, 'publish']);
    Route::post('/{id}/unpublish', [\App\Http\Controllers\Api\ServiceRegistrationController::class, 'unpublish']);
    Route::post('/{id}/versions', [\App\Http\Controllers\Api\ServiceRegistrationController::class, 'createVersion']);
});

// Rutas específicas para el frontend de publicador
/////
Route::prefix('publicador')->group(function () {
    Route::get('/services', [\App\Http\Controllers\Api\PublisherServiceController::class, 'index']);
    Route::post('/services', [\App\Http\Controllers\Api\PublisherServiceController::class, 'store']);
    Route::post('/services/{slug}/duplicate', [\App\Http\Controllers\Api\PublisherServiceController::class, 'duplicate']);
});
