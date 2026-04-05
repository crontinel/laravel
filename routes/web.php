<?php

declare(strict_types=1);

use CronSentinel\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('cron-sentinel.middleware', ['web', 'auth']))
    ->prefix(config('cron-sentinel.path', 'cron-sentinel'))
    ->name('cron-sentinel.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/api/status', [DashboardController::class, 'apiStatus'])->name('api.status');
    });
