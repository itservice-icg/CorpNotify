<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/notifications/{notification}/export.csv', [NotificationController::class, 'exportCsv'])
        ->name('notifications.export.csv');
    Route::get('/notifications/{notification}/export.xls', [NotificationController::class, 'exportExcel'])
        ->name('notifications.export.excel');
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::middleware('role:admin,manager')->group(function () {
        Route::resource('notifications', NotificationController::class)->only(['create', 'store', 'edit', 'update']);
        Route::patch('/notifications/{notification}/deactivate', [NotificationController::class, 'deactivate'])
            ->name('notifications.deactivate');
    });

    Route::resource('notifications', NotificationController::class)->only(['index', 'show']);
});
