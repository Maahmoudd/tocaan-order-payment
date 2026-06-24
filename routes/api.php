<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::controller(AuthController::class)->group(function (): void {
        Route::post('register', 'register')->name('auth.register');
        Route::post('login', 'login')->name('auth.login');
        Route::post('refresh', 'refresh')->name('auth.refresh');

        Route::middleware('auth:api')->group(function (): void {
            Route::post('logout', 'logout')->name('auth.logout');
            Route::get('me', 'me')->name('auth.me');
        });
    });
});
