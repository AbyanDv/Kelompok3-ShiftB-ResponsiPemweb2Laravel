<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\KasTypeController;
use App\Http\Controllers\Api\LedgerEntryController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::post('/webhooks/payment', [PaymentController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('admin')->apiResource('users', UserController::class);

    Route::middleware('verified')->group(function () {
        Route::get('/kas-types', [KasTypeController::class, 'index']);
        Route::get('/kas-types/{kasType}', [KasTypeController::class, 'show']);
        Route::get('/kas-types/{kasType}/bills', [KasTypeController::class, 'bills']);
        Route::get('/bills', [BillController::class, 'index']);
        Route::get('/bills/{bill}', [BillController::class, 'show']);
        Route::post('/bills/{bill}/payments', [PaymentController::class, 'storeForBill']);
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);
        Route::post('/payments/{payment}/simulate', [PaymentController::class, 'simulate']);
        Route::get('/ledger-entries', [LedgerEntryController::class, 'index']);
        Route::get('/ledger-entries/{ledgerEntry}', [LedgerEntryController::class, 'show']);
        Route::get('/ledger-summary', [LedgerEntryController::class, 'summary']);

        Route::middleware('admin')->group(function () {
            Route::post('/kas-types', [KasTypeController::class, 'store']);
            Route::put('/kas-types/{kasType}', [KasTypeController::class, 'update']);
            Route::delete('/kas-types/{kasType}', [KasTypeController::class, 'destroy']);
            Route::delete('/bills/{bill}', [BillController::class, 'destroy']);
            Route::post('/bills/{bill}/manual-payments', [PaymentController::class, 'storeManual']);
            Route::post('/ledger-entries', [LedgerEntryController::class, 'store']);
            Route::put('/ledger-entries/{ledgerEntry}', [LedgerEntryController::class, 'update']);
            Route::delete('/ledger-entries/{ledgerEntry}', [LedgerEntryController::class, 'destroy']);
            Route::post('/reminders', [ReminderController::class, 'send']);
        });
    });
});
