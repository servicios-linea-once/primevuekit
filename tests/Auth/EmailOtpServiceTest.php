<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PrimeVueKit\Auth\EmailOtpService;
use PrimeVueKit\Auth\Models\EmailOtpCode;
use PrimeVueKit\Tests\Fixtures\AuthUser;

function otpService(int $maxAttempts = 5, int $ttl = 300, int $resendAfter = 60, int $maxPerHour = 5): EmailOtpService
{
    return new EmailOtpService(
        length: 6,
        ttl: $ttl,
        maxAttempts: $maxAttempts,
        resendAfter: $resendAfter,
        maxPerHour: $maxPerHour,
    );
}

it('emite un código de seis dígitos y lo guarda hasheado', function (): void {
    $user = AuthUser::seed();

    $code = otpService()->issue($user);

    expect($code)->toMatch('/^\d{6}$/');

    $record = EmailOtpCode::query()->firstOrFail();

    expect($record->code_hash)->not->toBe($code)
        ->and(Hash::check($code, $record->code_hash))->toBeTrue()
        ->and($record->attempts)->toBe(0)
        ->and($record->consumed_at)->toBeNull();
});

it('consume el código al verificarlo y no admite reutilizarlo', function (): void {
    $user = AuthUser::seed();
    $service = otpService();
    $code = $service->issue($user);

    expect($service->verify($user, $code))->toBeTrue()
        ->and(EmailOtpCode::query()->firstOrFail()->consumed_at)->not->toBeNull()
        ->and($service->verify($user, $code))->toBeFalse();
});

it('rechaza un código caducado', function (): void {
    $user = AuthUser::seed();
    $service = otpService(ttl: 300);
    $code = $service->issue($user);

    Carbon::setTestNow(Carbon::now()->addSeconds(301));

    expect($service->verify($user, $code))->toBeFalse();

    Carbon::setTestNow();
});

it('cuenta los intentos fallidos y bloquea al alcanzar el máximo', function (): void {
    $user = AuthUser::seed();
    $service = otpService(maxAttempts: 3);
    $code = $service->issue($user);

    foreach (range(1, 3) as $attempt) {
        expect($service->verify($user, '000000'))->toBeFalse();
    }

    expect(EmailOtpCode::query()->firstOrFail()->attempts)->toBe(3)
        // Aunque ahora envíe el código correcto, el registro ya está agotado.
        ->and($service->verify($user, $code))->toBeFalse();
});

it('invalida el código anterior al emitir uno nuevo', function (): void {
    $user = AuthUser::seed();
    $service = otpService(resendAfter: 0);

    $first = $service->issue($user);
    $second = $service->issue($user);

    expect(EmailOtpCode::query()->count())->toBe(1)
        ->and($service->verify($user, $first))->toBeFalse();

    $service->invalidatePending($user);
    expect(EmailOtpCode::query()->count())->toBe(0)
        ->and($second)->toMatch('/^\d{6}$/');
});

it('limita el reenvío durante la ventana configurada', function (): void {
    $user = AuthUser::seed();
    $service = otpService(resendAfter: 60);

    expect($service->canIssue($user))->toBeTrue();

    $service->issue($user);

    expect($service->canIssue($user))->toBeFalse()
        ->and($service->secondsUntilResend($user))->toBeGreaterThan(0);
});

it('limita el número de emisiones por hora', function (): void {
    $user = AuthUser::seed();
    $service = otpService(resendAfter: 0, maxPerHour: 2);

    $service->issue($user);
    $service->issue($user);

    expect($service->canIssue($user))->toBeFalse();

    RateLimiter::clear('primevuekit:otp:hourly:'.$user->getKey());

    expect($service->canIssue($user))->toBeTrue();
});
