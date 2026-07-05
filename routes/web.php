<?php

use App\Http\Controllers\AdminCompanyController;
use App\Http\Controllers\BankCustomerController;
use App\Http\Controllers\CustomerAttachController;
use App\Http\Controllers\CustomerLinkController;
use App\Http\Controllers\CustomersStatusController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PerusahaanController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SecureFileController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierAttachController;
use App\Http\Controllers\SupplierLinkController;
use App\Http\Controllers\SuppliersStatusController;
use App\Http\Controllers\BankSupplierController;
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
    Route::get('/supplier-status-check', [SuppliersStatusController::class, 'index']);

    Route::resource('customer', CustomerController::class);
    Route::post('/customer/import-csv', [CustomerController::class, 'importCsv'])->name('customer.import-csv');
    Route::resource('customer-attachments', CustomerAttachController::class);
    // web.php
    Route::get('/customer/{id}/pdf', [CustomerController::class, 'generatePdf'])->name('customer.pdf');

    Route::post('/customer-links', [CustomerLinkController::class, 'store'])->name('customer-links.store');
    Route::get('/perusahaan/{id}/has-manager', [PerusahaanController::class, 'checkManagerExistence']);

    Route::resource('users', UserController::class);
    Route::post('/users/import-csv', [UserController::class, 'importCsv']);
    
    Route::resource('role-manager', RoleController::class);
    Route::resource('perusahaan', PerusahaanController::class);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    Route::get('bank-customer', [BankCustomerController::class, 'index'])->name('bank-customer.index');
    Route::get('bank-supplier', [BankSupplierController::class, 'index'])->name('bank-supplier.index');

    Route::post('/submit-supplier-status', [SuppliersStatusController::class, 'submit'])->name('supplier-status.submit');
    Route::post('/supplier/check-npwp', [SupplierController::class, 'checkNpwp'])->name('supplier.check-npwp');
    Route::resource('supplier', SupplierController::class);
    Route::post('/supplier/import-csv', [SupplierController::class, 'importCsv'])->name('supplier.import-csv');
    Route::resource('supplier-attachments', SupplierAttachController::class);
    Route::get('/supplier/{id}/pdf', [SupplierController::class, 'generatePdf'])->name('supplier.pdf');
    Route::post('/supplier-links', [SupplierLinkController::class, 'store'])->name('supplier-links.store');

    // Admin: pilih perusahaan aktif (session-based)
    Route::post('/admin/set-company', [AdminCompanyController::class, 'setCompany'])->name('admin.set-company');
});

Route::post('customer/upload-temp', [CustomerController::class, 'upload'])->name('customer.upload');
Route::post('customer/process-attachment', [CustomerController::class, 'processAttachment'])->name('customer.process-attachment');
Route::get('/form/{token}', [CustomerController::class, 'showPublicForm'])->name('customer.form.show');
Route::post('/form/{token}', [CustomerController::class, 'submitPublicForm'])->name('customer.form.submit');
Route::post('customer/store-public', [CustomerController::class, 'storePublic'])->name('customer.public.submit');

Route::post('supplier/upload-temp', [SupplierController::class, 'upload'])->name('supplier.upload');
Route::post('supplier/process-attachment', [SupplierController::class, 'processAttachment'])->name('supplier.process-attachment');
Route::get('/form-supplier/{token}', [SupplierController::class, 'showPublicForm'])->name('supplier.form.show');
Route::post('/form-supplier/{token}', [SupplierController::class, 'submitPublicForm'])->name('supplier.form.submit');
Route::post('supplier/store-public', [SupplierController::class, 'storePublic'])->name('supplier.public.submit');

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
