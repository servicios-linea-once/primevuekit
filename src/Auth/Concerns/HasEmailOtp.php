<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Concerns;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Notification;
use PrimeVueKit\Auth\Contracts\SupportsEmailOtp;
use PrimeVueKit\Auth\EmailOtpService;
use PrimeVueKit\Auth\Notifications\OneTimePassword;

/**
 * Segundo factor mediante código de un solo uso enviado por correo.
 *
 * @phpstan-require-extends User
 *
 * @phpstan-require-implements SupportsEmailOtp
 *
 * @property bool $email_otp_enabled
 */
trait HasEmailOtp
{
    public function hasEnabledEmailOtp(): bool
    {
        return (bool) $this->email_otp_enabled;
    }

    public function enableEmailOtp(): void
    {
        $this->forceFill(['email_otp_enabled' => true])->save();
    }

    public function disableEmailOtp(): void
    {
        $this->emailOtpService()->invalidatePending($this);

        $this->forceFill(['email_otp_enabled' => false])->save();
    }

    /**
     * Emite y envía un código. Devuelve false si el límite de reenvíos lo impide.
     */
    public function sendEmailOtp(): bool
    {
        $service = $this->emailOtpService();

        if (! $service->canIssue($this)) {
            return false;
        }

        Notification::send($this, new OneTimePassword($service->issue($this), $service->ttl()));

        return true;
    }

    public function verifyEmailOtp(string $code): bool
    {
        return $this->emailOtpService()->verify($this, $code);
    }

    public function secondsUntilEmailOtpResend(): int
    {
        return $this->emailOtpService()->secondsUntilResend($this);
    }

    private function emailOtpService(): EmailOtpService
    {
        return app(EmailOtpService::class);
    }
}
