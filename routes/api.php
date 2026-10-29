<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DriverAvailabilityController;
use App\Http\Controllers\DriverOnboardingController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Prefix /api/v1 is set in bootstrap/app.php.

Route::prefix('auth')->group(function () {
    Route::post('otp/request', [OtpController::class, 'request'])->middleware('throttle:20,1');
    Route::post('otp/verify', [OtpController::class, 'verify'])->middleware('throttle:20,1');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    // Available to suspended accounts so the app can show the blocked screen.
    Route::get('users/me', [UserController::class, 'me']);

    // Protected features: suspended accounts are rejected here.
    Route::middleware('account.active')->group(function () {
        Route::post('uploads', [UploadController::class, 'store'])->middleware('throttle:60,1');

        Route::prefix('drivers')->middleware('role:driver')->group(function () {
            Route::get('me/status', [DriverOnboardingController::class, 'status']);
            Route::put('register/step1', [DriverOnboardingController::class, 'step1']);
            Route::put('register/step2', [DriverOnboardingController::class, 'step2']);
            Route::put('register/step3', [DriverOnboardingController::class, 'step3']);
            Route::post('register/submit', [DriverOnboardingController::class, 'submit']);

            // Independent approval gate, regardless of what the client believes.
            Route::put('me/online', [DriverAvailabilityController::class, 'update'])->middleware('driver.approved');
        });

        // Legacy aliases from the spec's "logical endpoints". Remove once the Flutter app uses the paths above.
        Route::prefix('driver')->middleware('role:driver')->group(function () {
            Route::put('register/step-1', [DriverOnboardingController::class, 'step1']);
            Route::put('register/step-2', [DriverOnboardingController::class, 'step2']);
            Route::put('register/step-3', [DriverOnboardingController::class, 'step3']);
            Route::post('register/submit', [DriverOnboardingController::class, 'submit']);
            Route::get('verification/status', [DriverOnboardingController::class, 'status']);
        });
    });
});
