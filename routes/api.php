<?php

declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function (): void {
    Route::apiResource('employees', Controllers\EmployeeController::class);
    Route::controller(Controllers\AuthController::class)
        ->prefix('auth')
        ->group(function (): void {
            Route::post('login', 'login')->withoutMiddleware('auth:api');
            Route::post('refresh', 'refresh')->withoutMiddleware('auth:api');
            Route::delete('logout', 'logout');
        });
});
