<?php

declare(strict_types=1);

namespace PrimeVueKit\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PrimeVueKit\Auth\Concerns\HasEmailOtp;
use PrimeVueKit\Auth\Concerns\HasTotp;
use PrimeVueKit\Auth\Contracts\SupportsEmailOtp;
use PrimeVueKit\Auth\Contracts\SupportsTotp;

/**
 * Modelo de usuario mínimo para las pruebas de los traits de segundo factor.
 */
class AuthUser extends Authenticatable implements SupportsEmailOtp, SupportsTotp
{
    use HasEmailOtp;
    use HasTotp;
    use Notifiable;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_otp_enabled' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * No se llama `make()` para no ensombrecer el método estático de Eloquent.
     */
    public static function seed(string $email = 'ana@example.test'): self
    {
        return self::query()->create([
            'name' => 'Ana',
            'email' => $email,
            'password' => 'password',
        ]);
    }
}
