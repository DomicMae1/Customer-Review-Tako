<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerDataController;
use App\Http\Controllers\Api\IntegrationTokenController;
use App\Http\Controllers\Api\ExternalCustomerReceiveController;

Route::post('/integration/token', [IntegrationTokenController::class, 'store']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/customer/data', [CustomerDataController::class, 'index']);
    Route::post('/customer/receive', [ExternalCustomerReceiveController::class, 'store']);
});