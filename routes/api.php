<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerDataController;
use App\Http\Controllers\Api\SupplierDataController;
use App\Http\Controllers\Api\IntegrationTokenController;
use App\Http\Controllers\Api\ExternalCustomerReceiveController;
use App\Http\Controllers\Api\ExternalSupplierReceiveController;

Route::post('/integration/token', [IntegrationTokenController::class, 'store']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/customer/data', [CustomerDataController::class, 'index']);
    Route::post('/customer/receive', [ExternalCustomerReceiveController::class, 'store']);

    Route::get('/supplier/data', [SupplierDataController::class, 'index']);
    Route::post('/supplier/receive', [ExternalSupplierReceiveController::class, 'store']);
});