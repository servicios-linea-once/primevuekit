<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PrimeVueKit\Auth\Models\EmailOtpCode;

/**
 * Emisión y verificación de códigos de un solo uso enviados por correo.
 *
 * El código nunca se persiste en claro: sólo se guarda su hash. El valor en claro
 * lo devuelve `issue()` para que el llamador lo entregue y se descarta después.
 */
final class EmailOtpService
{
    public function __construct(
        private readonly int $length = 6,
        private readonly int $ttl = 300,
        private readonly int $maxAttempts = 5,
        private readonly int $resendAfter = 60,
        private readonly int $maxPerHour = 5,
    ) {}

    /**
     * Emite un código nuevo y descarta los pendientes del usuario.
     */
    public function issue(Authenticatable $user): string
    {
        $this->invalidatePending($user);

        $code = $this->generateCode();

        EmailOtpCode::query()->create([
            'user_id' => $user->getAuthIdentifier(),
            'code_hash' => Hash::make($code),
            'expires_at' => Carbon::now()->addSeconds($this->ttl),
        ]);

        RateLimiter::hit($this->resendKey($user), $this->resendAfter);
        RateLimiter::hit($this->hourlyKey($user), 3600);

        return $code;
    }

    /**
     * Verifica el código pendiente más reciente. Un fallo consume un intento.
     */
    public function verify(Authenticatable $user, string $code): bool
    {
        $record = $this->pendingCode($user);

        if ($record === null || ! $record->isUsable($this->maxAttempts)) {
            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return false;
        }

        $record->forceFill(['consumed_at' => Carbon::now()])->save();

        RateLimiter::clear($this->resendKey($user));
        RateLimiter::clear($this->hourlyKey($user));

        return true;
    }

    public function invalidatePending(Authenticatable $user): void
    {
        EmailOtpCode::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->pending()
            ->delete();
    }

    public function pendingCode(Authenticatable $user): ?EmailOtpCode
    {
        return EmailOtpCode::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->pending()
            ->latest('id')
            ->first();
    }

    /**
     * Segundos que faltan para poder reenviar. Cero si se puede reenviar ya.
     */
    public function secondsUntilResend(Authenticatable $user): int
    {
        $key = $this->resendKey($user);

        return RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0;
    }

    public function canIssue(Authenticatable $user): bool
    {
        return $this->secondsUntilResend($user) === 0
            && ! RateLimiter::tooManyAttempts($this->hourlyKey($user), $this->maxPerHour);
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    private function generateCode(): string
    {
        $code = random_int(0, 10 ** $this->length - 1);

        return str_pad((string) $code, $this->length, '0', STR_PAD_LEFT);
    }

    private function resendKey(Authenticatable $user): string
    {
        return 'primevuekit:otp:resend:'.$user->getAuthIdentifier();
    }

    private function hourlyKey(Authenticatable $user): string
    {
        return 'primevuekit:otp:hourly:'.$user->getAuthIdentifier();
    }
}
