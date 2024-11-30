<?php

use App\Http\Controllers\API\Auth\LoginController;
use App\Http\Controllers\API\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/login', [LoginController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('/orders', OrderController::class)->only(['store']);
    });

});

