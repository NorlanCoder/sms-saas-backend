<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SenderIdController;
use App\Http\Controllers\Api\SmsLogController;
use App\Http\Controllers\Api\SmsController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminCountryController;
use App\Http\Controllers\Admin\AdminSenderIdController;
use App\Http\Controllers\Admin\AdminSmsLogController;
use App\Http\Controllers\Admin\AdminTransactionController;
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

        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        // Logs SMS
        Route::get('/sms/logs', [SmsLogController::class, 'index']);

        // Rapports
        Route::get('/reports/export', [ReportController::class, 'export']);

        // Gestion des pays
        Route::prefix('countries')->group(function () {
            Route::get('/active', [CountryController::class, 'active']);
            Route::post('/activate', [CountryController::class, 'activate']);
            Route::post('/deactivate', [CountryController::class, 'deactivate']);
        });

        // Gestion des SENDER_IDs
        Route::prefix('sender-ids')->group(function () {
            Route::get('/', [SenderIdController::class, 'index']);
            Route::post('/', [SenderIdController::class, 'store']);
            Route::get('/{id}', [SenderIdController::class, 'show']);
            Route::delete('/{id}', [SenderIdController::class, 'destroy']);
        });
    });
});

// Envoi SMS — protégé par signature RSA + rate limiting
Route::middleware(['verify.api.signature', 'throttle:60,1'])->prefix('v1')->group(function () {
    Route::post('/send-sms', [SmsController::class, 'send']);
});

// Statut batch — protégé par Sanctum
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/sms/status/{batchId}', [SmsController::class, 'batchStatus']);
});

// Routes admin publiques
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
});

// Routes admin protégées
Route::prefix('admin')->middleware(['auth:sanctum', 'is.admin'])->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout']);

    // Gestion des companies
    Route::get('/companies', [AdminCompanyController::class, 'index']);
    Route::get('/companies/{id}', [AdminCompanyController::class, 'show']);
    Route::put('/companies/{id}/status', [AdminCompanyController::class, 'updateStatus']);

    // Gestion des SENDER_IDs
    Route::get('/sender-ids', [AdminSenderIdController::class, 'index']);
    Route::put('/sender-ids/{id}/approve', [AdminSenderIdController::class, 'approve']);
    Route::put('/sender-ids/{id}/reject', [AdminSenderIdController::class, 'reject']);
    Route::put('/sender-ids/{id}/suspend', [AdminSenderIdController::class, 'suspend']);

    // Gestion des pays
    Route::get('/countries', [AdminCountryController::class, 'index']);
    Route::put('/countries/{id}/tariff', [AdminCountryController::class, 'updateTariff']);
    Route::put('/countries/{id}/status', [AdminCountryController::class, 'updateStatus']);

    // Logs SMS globaux
    Route::get('/sms/logs', [AdminSmsLogController::class, 'index']);

    // Transactions globales
    Route::get('/transactions', [AdminTransactionController::class, 'index']);
});
