<?php

declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::apiResource('employees', Controllers\EmployeeController::class);
Route::prefix('auth')->controller(Controllers\AuthController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::post('login', 'login')->withoutMiddleware('auth:api');
        Route::post('refresh', 'refresh')->withoutMiddleware('auth:api');
        Route::delete('logout', 'logout');
    });
