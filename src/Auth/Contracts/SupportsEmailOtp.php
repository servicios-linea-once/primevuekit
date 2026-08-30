<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Contracts;

use PrimeVueKit\Auth\Concerns\HasEmailOtp;

/**
 * Lo que aporta {@see HasEmailOtp}.
 *
 * El comando añade este contrato al modelo de usuario junto con el trait, para que los
 * controladores puedan comprobarlo con `instanceof` en lugar de adivinar con `method_exists`.
 */
interface SupportsEmailOtp
{
    public function hasEnabledEmailOtp(): bool;

    public function enableEmailOtp(): void;

    public function disableEmailOtp(): void;

    public function sendEmailOtp(): bool;

    public function verifyEmailOtp(string $code): bool;

    public function secondsUntilEmailOtpResend(): int;
}
