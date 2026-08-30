<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * Valida las credenciales.
     *
     * Devuelve el usuario cuando falta superar un segundo factor, o null si la sesión
     * ya quedó autenticada. Se usa `attemptWhen` para que el guard compare la contraseña
     * en tiempo constante y dispare sus eventos, pero sin crear sesión si hace falta reto.
     *
     * @throws ValidationException
     */
    public function authenticate(): ?Authenticatable
    {
        $this->ensureIsNotRateLimited();

        $pending = null;

        $authenticated = Auth::attemptWhen(
            $this->only('email', 'password'),
            function (Authenticatable $user) use (&$pending): bool {
                if (self::requiresChallenge($user)) {
                    $pending = $user;

                    return false;
                }

                return true;
            },
            $this->boolean('remember'),
        );

        if ($pending !== null) {
            RateLimiter::clear($this->throttleKey());

            return $pending;
        }

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());

        return null;
    }

    /**
     * Reconoce tanto los traits del kit como el de Fortify, para que la capa de OTP
     * funcione con cualquiera de las dos estrategias.
     */
    public static function requiresChallenge(Authenticatable $user): bool
    {
        foreach (['hasEnabledTotp', 'hasEnabledTwoFactorAuthentication', 'hasEnabledEmailOtp'] as $method) {
            if (method_exists($user, $method) && $user->{$method}() === true) {
                return true;
            }
        }

        return false;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }
}
