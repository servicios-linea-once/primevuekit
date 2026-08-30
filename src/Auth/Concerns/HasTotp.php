<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Concerns;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PrimeVueKit\Auth\Contracts\SupportsTotp;
use PrimeVueKit\Auth\QrCodeSvg;
use PrimeVueKit\Auth\TotpService;

/**
 * Segundo factor TOTP (RFC 6238) con códigos de recuperación.
 *
 * Usa los mismos nombres de columna que laravel/fortify, más
 * `two_factor_last_used_counter`, que es propia del kit y evita que un código pueda
 * reutilizarse dentro de la ventana de tolerancia.
 *
 * @phpstan-require-extends User
 *
 * @phpstan-require-implements SupportsTotp
 *
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_used_counter
 */
trait HasTotp
{
    public function hasEnabledTotp(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function hasPendingTotp(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at === null;
    }

    /**
     * Genera secreto y códigos de recuperación, pero deja el factor sin activar:
     * hace falta confirmarlo con un código válido para que empiece a exigirse.
     */
    public function startTotpEnrolment(): void
    {
        $this->forceFill([
            'two_factor_secret' => Crypt::encryptString($this->totpService()->generateSecret()),
            'two_factor_recovery_codes' => $this->encodeRecoveryCodes($this->freshRecoveryCodes()),
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_counter' => null,
        ])->save();
    }

    public function confirmTotp(string $code): bool
    {
        if (! $this->hasPendingTotp() || ! $this->verifyTotpCode($code)) {
            return false;
        }

        $this->forceFill(['two_factor_confirmed_at' => Carbon::now()])->save();

        return true;
    }

    /**
     * Verifica un código del autenticador y guarda su contador para impedir reutilizarlo.
     */
    public function verifyTotpCode(string $code): bool
    {
        if ($this->two_factor_secret === null) {
            return false;
        }

        $counter = $this->totpService()->verify(
            $this->totpSecret(),
            $code,
            $this->two_factor_last_used_counter ?? 0,
        );

        if ($counter === null) {
            return false;
        }

        $this->forceFill(['two_factor_last_used_counter' => $counter])->save();

        return true;
    }

    /**
     * Consume un código de recuperación. Se elimina de la lista al usarse.
     */
    public function useTotpRecoveryCode(string $code): bool
    {
        $codes = $this->totpRecoveryCodes();

        $remaining = array_values(array_filter(
            $codes,
            static fn (string $candidate): bool => ! hash_equals($candidate, $code),
        ));

        if (count($remaining) === count($codes)) {
            return false;
        }

        $this->forceFill(['two_factor_recovery_codes' => $this->encodeRecoveryCodes($remaining)])->save();

        return true;
    }

    public function regenerateTotpRecoveryCodes(): void
    {
        $this->forceFill([
            'two_factor_recovery_codes' => $this->encodeRecoveryCodes($this->freshRecoveryCodes()),
        ])->save();
    }

    public function disableTotp(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_counter' => null,
        ])->save();
    }

    public function totpSecret(): string
    {
        return Crypt::decryptString((string) $this->two_factor_secret);
    }

    public function totpProvisioningUri(): string
    {
        return $this->totpService()->provisioningUri(
            $this->totpSecret(),
            (string) $this->getAttribute('email'),
        );
    }

    public function totpQrCodeSvg(): string
    {
        return app(QrCodeSvg::class)->render($this->totpProvisioningUri());
    }

    /**
     * @return list<string>
     */
    public function totpRecoveryCodes(): array
    {
        if ($this->two_factor_recovery_codes === null) {
            return [];
        }

        $decoded = json_decode(Crypt::decryptString($this->two_factor_recovery_codes), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_string'));
    }

    /**
     * @return list<string>
     */
    private function freshRecoveryCodes(): array
    {
        $total = (int) config('primevuekit.auth.totp.recovery_codes', 8);

        return array_map(
            static fn (): string => Str::random(10).'-'.Str::random(10),
            range(1, max(1, $total)),
        );
    }

    /**
     * @param  list<string>  $codes
     */
    private function encodeRecoveryCodes(array $codes): string
    {
        return Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR));
    }

    private function totpService(): TotpService
    {
        return app(TotpService::class);
    }
}
