<?php

declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::apiResource('employees', Controllers\EmployeeController::class);
