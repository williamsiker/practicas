<?php

use App\Http\Controllers\Api\PublisherServiceController;
use App\Http\Controllers\Api\ServiceApprovalBySlugController;
use App\Http\Controllers\Api\ServiceCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['status' => 'ok']));

Route::prefix('publicador')->group(function () {
    Route::get('services', [PublisherServiceController::class, 'index']);
    Route::post('services', [PublisherServiceController::class, 'store']);
    Route::post('services/{slug}/duplicate', [PublisherServiceController::class, 'duplicate']);
});

Route::prefix('consumidor')->group(function () {
    Route::get('services', [ServiceCatalogController::class, 'getServiceCatalog']);
    Route::get('services/{id}', [ServiceCatalogController::class, 'getServiceDetails']);
    Route::post('services/{slug}/versions/{versionId}/requests', [ServiceCatalogController::class, 'createServiceRequest']);
});

Route::prefix('admin')->group(function () {
    Route::get('services/pending', [ServiceApprovalBySlugController::class, 'index']);
    Route::post('services/{slug}/approve', [ServiceApprovalBySlugController::class, 'approve']);
    Route::post('services/{slug}/reject', [ServiceApprovalBySlugController::class, 'reject']);
    Route::patch('services/{slug}/endpoint', [ServiceApprovalBySlugController::class, 'updateEndpoint']);
});
