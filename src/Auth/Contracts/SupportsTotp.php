<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Contracts;

use PrimeVueKit\Auth\Concerns\HasTotp;

/**
 * Lo que aporta {@see HasTotp}.
 *
 * Con la estrategia `fortify` el modelo NO implementa este contrato: allí el segundo
 * factor de aplicación lo gestiona `Laravel\Fortify\TwoFactorAuthenticatable`.
 */
interface SupportsTotp
{
    public function hasEnabledTotp(): bool;

    public function hasPendingTotp(): bool;

    public function startTotpEnrolment(): void;

    public function confirmTotp(string $code): bool;

    public function verifyTotpCode(string $code): bool;

    public function useTotpRecoveryCode(string $code): bool;

    public function regenerateTotpRecoveryCodes(): void;

    public function disableTotp(): void;

    public function totpSecret(): string;

    public function totpProvisioningUri(): string;

    public function totpQrCodeSvg(): string;

    /**
     * @return list<string>
     */
    public function totpRecoveryCodes(): array;
}
