<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::controller(AuthController::class)->group(function (): void {
        Route::post('register', 'register')->middleware('throttle:auth')->name('auth.register');
        Route::post('login', 'login')->middleware('throttle:auth')->name('auth.login');
        Route::post('refresh', 'refresh')->middleware('throttle:refresh')->name('auth.refresh');

        Route::middleware('auth:api')->group(function (): void {
            Route::post('logout', 'logout')->name('auth.logout');
            Route::get('me', 'me')->name('auth.me');
        });
    });

    Route::middleware('auth:api')->apiResource('orders', OrderController::class);

    Route::middleware('auth:api')->group(function (): void {
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])
            ->name('orders.confirm');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
            ->name('orders.cancel');
        Route::post('orders/{order}/payments', [PaymentController::class, 'store'])
            ->middleware('throttle:payments')
            ->name('orders.payments.store');
        Route::get('orders/{order}/payments', [PaymentController::class, 'forOrder'])
            ->name('orders.payments.index');
        Route::get('payments', [PaymentController::class, 'index'])
            ->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])
            ->name('payments.show');
    });
});
