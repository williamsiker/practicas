<?php

use App\Http\Controllers\Consumidor\ServiceController as ConsumidorServiceController;
use App\Http\Controllers\Consumidor\ServiceRequestController as ConsumidorServiceRequestController;
use App\Http\Controllers\Publicador\ServiceController as PublicadorServiceController;
use App\Http\Controllers\Admin\ServiceApprovalController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['status' => 'ok']));

Route::prefix('publicador')->group(function () {
    Route::get('services', [PublicadorServiceController::class, 'index']);
    Route::post('services', [PublicadorServiceController::class, 'store']);
    Route::get('services/{service:slug}', [PublicadorServiceController::class, 'show']);
    Route::post('services/{service:slug}/duplicate', [PublicadorServiceController::class, 'duplicate']);
});

Route::prefix('consumidor')->group(function () {
    Route::get('services', [ConsumidorServiceController::class, 'index']);
    Route::get('services/{service:slug}', [ConsumidorServiceController::class, 'show']);
    Route::post(
        'services/{service:slug}/versions/{version}/requests',
        [ConsumidorServiceRequestController::class, 'store']
    );
});

Route::prefix('admin')->group(function () {
    Route::get('services/pending', [ServiceApprovalController::class, 'pending']);
    Route::post('services/{service:slug}/approve', [ServiceApprovalController::class, 'approve']);
    Route::post('services/{service:slug}/reject', [ServiceApprovalController::class, 'reject']);
});
