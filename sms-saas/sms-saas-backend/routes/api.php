<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CreditController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Gestion des pays (publique)
    Route::prefix('countries')->group(function () {
        Route::get('/', [CountryController::class, 'index']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);

        // Gestion des clés API
        Route::get('/keys', [ApiKeyController::class, 'index']);
        Route::post('/keys/regenerate', [ApiKeyController::class, 'regenerate']);

        // Gestion des crédits
        Route::prefix('credits')->group(function () {
            Route::get('/balance', [CreditController::class, 'balance']);
            Route::post('/recharge', [CreditController::class, 'recharge']);
            Route::get('/transactions', [CreditController::class, 'transactions']);
        });

        // Gestion des pays
        Route::prefix('countries')->group(function () {
            Route::get('/active', [CountryController::class, 'active']);
            Route::post('/activate', [CountryController::class, 'activate']);
            Route::post('/deactivate', [CountryController::class, 'deactivate']);
        });
    });
});
