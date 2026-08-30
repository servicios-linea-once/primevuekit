<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PrimeVueKit\Auth\Http\Controllers\AuthenticatedSessionController;
use PrimeVueKit\Auth\Http\Controllers\ConfirmablePasswordController;
use PrimeVueKit\Auth\Http\Controllers\EmailOtpChallengeController;
use PrimeVueKit\Auth\Http\Controllers\EmailOtpSettingsController;
use PrimeVueKit\Auth\Http\Controllers\EmailVerificationController;
use PrimeVueKit\Auth\Http\Controllers\PasswordResetController;
use PrimeVueKit\Auth\Http\Controllers\RegisteredUserController;
use PrimeVueKit\Auth\Http\Controllers\TwoFactorChallengeController;
use PrimeVueKit\Auth\Http\Controllers\TwoFactorSettingsController;
use PrimeVueKit\Auth\Http\Middleware\EnsureChallengeIsPending;

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.store');
});

// Retos de segundo factor: hay credenciales validadas pero todavía no hay sesión.
Route::middleware(EnsureChallengeIsPending::class)->group(function (): void {
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:6,1');

    Route::get('otp-challenge', [EmailOtpChallengeController::class, 'create'])->name('otp.challenge');
    Route::post('otp-challenge', [EmailOtpChallengeController::class, 'store'])
        ->middleware('throttle:6,1');
    Route::post('otp-challenge/resend', [EmailOtpChallengeController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('otp.resend');
});

Route::middleware('auth')->group(function (): void {
    Route::get('verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Cambiar el segundo factor exige reconfirmar la contraseña.
    Route::middleware('password.confirm')->group(function (): void {
        Route::get('user/two-factor', [TwoFactorSettingsController::class, 'show'])->name('two-factor.show');
        Route::post('user/two-factor', [TwoFactorSettingsController::class, 'enable'])->name('two-factor.enable');
        Route::post('user/two-factor/confirm', [TwoFactorSettingsController::class, 'confirm'])->name('two-factor.confirm');
        Route::post('user/two-factor/recovery-codes', [TwoFactorSettingsController::class, 'regenerate'])->name('two-factor.recovery-codes');
        Route::delete('user/two-factor', [TwoFactorSettingsController::class, 'disable'])->name('two-factor.disable');

        Route::post('user/email-otp', [EmailOtpSettingsController::class, 'enable'])->name('email-otp.enable');
        Route::delete('user/email-otp', [EmailOtpSettingsController::class, 'disable'])->name('email-otp.disable');
    });
});
