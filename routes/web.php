<?php

use App\Http\Controllers\CustomerAttachController;
use App\Http\Controllers\CustomerLinkController;
use App\Http\Controllers\CustomersStatusController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PerusahaanController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SecureFileController;
use App\Models\Customers_Status;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\FileController;

Route::get('/', function () {
    // return Inertia::render('welcome');
    return redirect('customer');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return redirect('customer');
    });
    Route::get('customer/share', [CustomerController::class, 'share'])->name('customer.share');
    // Di routes/web.php
    Route::post('/submit-customer-status', [CustomersStatusController::class, 'submit'])->name('customer-status.submit');

    Route::post('/customer/check-npwp', [CustomerController::class, 'checkNpwp'])->name('customer.check-npwp');

    // Route::post('/send-customer-notification', [CustomerController::class, 'sendNotification'])->name('customer.sendNotification');

    Route::get('/customer-status-check', [CustomersStatusController::class, 'index']);

    Route::resource('customer', CustomerController::class);
    Route::resource('customer-attachments', CustomerAttachController::class);
    // web.php
    Route::get('/customer/{id}/pdf', [CustomerController::class, 'generatePdf'])->name('customer.pdf');

    Route::post('/customer-links', [CustomerLinkController::class, 'store'])->name('customer-links.store');
    Route::get('/perusahaan/{id}/has-manager', [PerusahaanController::class, 'checkManagerExistence']);

    Route::resource('users', UserController::class);
    Route::post('/users/import-csv', [UserController::class, 'importCsv']);
    
    Route::resource('role-manager', RoleController::class);
    Route::resource('perusahaan', PerusahaanController::class);
});

Route::post('customer/upload-temp', [CustomerController::class, 'upload'])->name('customer.upload');
Route::post('customer/process-attachment', [CustomerController::class, 'processAttachment'])->name('customer.process-attachment');
Route::get('/form/{token}', [CustomerController::class, 'showPublicForm'])->name('customer.form.show');
Route::post('/form/{token}', [CustomerController::class, 'submitPublicForm'])->name('customer.form.submit');
Route::post('customer/store-public', [CustomerController::class, 'storePublic'])->name('customer.public.submit');

Route::get('/file/view/{path}', [FileController::class, 'view'])->middleware('auth')
    ->where('path', '.*') 
    ->name('file.view');

Route::get('/customer/{path}', [FileController::class, 'view'])
    ->where('path', '.*') 
    ->name('file.view');

Route::get('/secure-attachment/{path}', [SecureFileController::class, 'show'])
        ->where('path', '.*')
        ->name('secure.attachment.show');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
