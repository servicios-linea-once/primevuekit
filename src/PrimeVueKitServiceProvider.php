<?php

declare(strict_types=1);

namespace PrimeVueKit;

use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;
use PrimeVueKit\Auth\EmailOtpService;
use PrimeVueKit\Auth\QrCodeSvg;
use PrimeVueKit\Auth\TotpService;
use PrimeVueKit\Console\Commands\AuthCommand;
use PrimeVueKit\Console\Commands\InstallCommand;

class PrimeVueKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'primevuekit');

        $this->registerAuthServices();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => $this->app->configPath('primevuekit.php'),
            ], 'primevuekit-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'primevuekit-auth-migrations');

            $this->commands([
                AuthCommand::class,
                InstallCommand::class,
            ]);
        }
    }

    protected function configPath(): string
    {
        return __DIR__.'/../config/primevuekit.php';
    }

    /**
     * Los tres servicios de segundo factor se construyen desde la configuración para
     * que ni los traits ni los controladores tengan que conocerla.
     */
    protected function registerAuthServices(): void
    {
        $this->app->singleton(TotpService::class, function (): TotpService {
            $issuer = config('primevuekit.auth.totp.issuer');

            return new TotpService(
                new Google2FA,
                issuer: is_string($issuer) && $issuer !== '' ? $issuer : (string) config('app.name', 'Laravel'),
                secretLength: (int) config('primevuekit.auth.totp.secret_length', 32),
                digits: (int) config('primevuekit.auth.totp.digits', 6),
                window: (int) config('primevuekit.auth.totp.window', 1),
            );
        });

        $this->app->singleton(QrCodeSvg::class, fn (): QrCodeSvg => new QrCodeSvg(
            size: (int) config('primevuekit.auth.totp.qr_size', 256),
            margin: (int) config('primevuekit.auth.totp.qr_margin', 1),
        ));

        $this->app->singleton(EmailOtpService::class, fn (): EmailOtpService => new EmailOtpService(
            length: (int) config('primevuekit.auth.otp.length', 6),
            ttl: (int) config('primevuekit.auth.otp.ttl', 300),
            maxAttempts: (int) config('primevuekit.auth.otp.max_attempts', 5),
            resendAfter: (int) config('primevuekit.auth.otp.resend_after', 60),
            maxPerHour: (int) config('primevuekit.auth.otp.max_per_hour', 5),
        ));
    }
}
