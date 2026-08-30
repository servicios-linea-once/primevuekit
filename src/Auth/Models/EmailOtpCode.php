<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Código de un solo uso enviado por correo como segundo factor.
 *
 * `code_hash` guarda el código hasheado; el valor en claro sólo existe en memoria
 * durante la petición que lo emite y en el correo que se envía.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property int $attempts
 */
class EmailOtpCode extends Model
{
    protected $table = 'email_otp_codes';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'code_hash', 'expires_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['code_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('consumed_at');
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function wasConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function hasAttemptsLeft(int $maxAttempts): bool
    {
        return $this->attempts < $maxAttempts;
    }

    public function isUsable(int $maxAttempts): bool
    {
        return ! $this->wasConsumed()
            && ! $this->hasExpired()
            && $this->hasAttemptsLeft($maxAttempts);
    }
}
