<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use PrimeVueKit\Auth\EmailOtpService;
use PrimeVueKit\Auth\Notifications\OneTimePassword;
use PrimeVueKit\Auth\TotpService;
use PrimeVueKit\Tests\Fixtures\AuthUser;

it('el enrolamiento deja el factor pendiente de confirmación', function (): void {
    $user = AuthUser::seed();

    $user->startTotpEnrolment();

    expect($user->hasPendingTotp())->toBeTrue()
        ->and($user->hasEnabledTotp())->toBeFalse()
        ->and($user->two_factor_secret)->not->toBeNull()
        // El secreto se guarda cifrado, nunca en claro.
        ->and($user->two_factor_secret)->not->toBe($user->totpSecret())
        ->and($user->totpRecoveryCodes())->toHaveCount(8);
});

it('confirma el factor con un código válido del autenticador', function (): void {
    $user = AuthUser::seed();
    $user->startTotpEnrolment();

    $code = app(TotpService::class)->currentCode($user->totpSecret());

    expect($user->confirmTotp($code))->toBeTrue()
        ->and($user->hasEnabledTotp())->toBeTrue()
        ->and($user->two_factor_confirmed_at)->not->toBeNull();
});

it('no confirma el factor con un código inválido', function (): void {
    $user = AuthUser::seed();
    $user->startTotpEnrolment();

    expect($user->confirmTotp('000000'))->toBeFalse()
        ->and($user->hasEnabledTotp())->toBeFalse();
});

it('guarda el contador del código usado para impedir reutilizarlo', function (): void {
    $user = AuthUser::seed();
    $user->startTotpEnrolment();

    $code = app(TotpService::class)->currentCode($user->totpSecret());

    expect($user->verifyTotpCode($code))->toBeTrue()
        ->and($user->two_factor_last_used_counter)->not->toBeNull()
        ->and($user->verifyTotpCode($code))->toBeFalse();
});

it('consume un código de recuperación al usarlo', function (): void {
    $user = AuthUser::seed();
    $user->startTotpEnrolment();

    $codes = $user->totpRecoveryCodes();

    expect($user->useTotpRecoveryCode($codes[0]))->toBeTrue()
        ->and($user->totpRecoveryCodes())->toHaveCount(7)
        ->and($user->useTotpRecoveryCode($codes[0]))->toBeFalse();
});

it('regenerar los códigos de recuperación sustituye la lista entera', function (): void {
    $user = AuthUser::seed();
    $user->startTotpEnrolment();

    $before = $user->totpRecoveryCodes();
    $user->regenerateTotpRecoveryCodes();

    expect($user->totpRecoveryCodes())->toHaveCount(8)
        ->and(array_intersect($before, $user->totpRecoveryCodes()))->toBeEmpty();
});

it('desactivar el factor limpia todas las columnas', function (): void {
    $user = AuthUser::seed();
    $user->startTotpEnrolment();
    $user->disableTotp();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->two_factor_last_used_counter)->toBeNull()
        ->and($user->hasEnabledTotp())->toBeFalse();
});

it('envía el código por correo y respeta el límite de reenvío', function (): void {
    Notification::fake();

    $user = AuthUser::seed();

    expect($user->hasEnabledEmailOtp())->toBeFalse();

    $user->enableEmailOtp();

    expect($user->hasEnabledEmailOtp())->toBeTrue()
        ->and($user->sendEmailOtp())->toBeTrue()
        // El segundo envío inmediato cae en la ventana de reenvío.
        ->and($user->sendEmailOtp())->toBeFalse();

    Notification::assertSentTimes(OneTimePassword::class, 1);
});

it('desactivar el OTP por correo descarta los códigos pendientes', function (): void {
    Notification::fake();

    $user = AuthUser::seed();
    $user->enableEmailOtp();
    $user->sendEmailOtp();

    $user->disableEmailOtp();

    expect($user->hasEnabledEmailOtp())->toBeFalse()
        ->and(app(EmailOtpService::class)->pendingCode($user))->toBeNull();
});
