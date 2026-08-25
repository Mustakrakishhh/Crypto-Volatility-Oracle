<?php

use App\Http\Controllers\OracleDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', OracleDashboardController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', OracleDashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
