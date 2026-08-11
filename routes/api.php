<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    /**
     * Exibe o usuário autenticado.
     *
     * @group Autenticação
     */
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');

    Route::delete('/user', [AuthController::class, 'destroy'])
        ->middleware('auth:sanctum')->name('user.destroy');

    Route::put('/user/password', [AuthController::class, 'updatePassword'])
        ->middleware('auth:sanctum')->name('user.password.update');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1')->name('register');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')->name('login');
        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->middleware('throttle:5,1')->name('refresh');
        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware(['auth:sanctum', 'throttle:5,1'])->name('logout');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:5,1')->name('password.email');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:5,1')->name('password.store');
    });

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::apiResource('tasks', TaskController::class)->names('tasks');
    });
});
