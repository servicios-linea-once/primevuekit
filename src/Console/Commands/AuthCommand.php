<?php

declare(strict_types=1);

namespace PrimeVueKit\Console\Commands;

use Illuminate\Console\Command;
use PrimeVueKit\Enums\AuthStrategy;
use PrimeVueKit\Enums\DependencyState;
use PrimeVueKit\Enums\PrimeVueLine;
use PrimeVueKit\Installer\AuthPublisher;
use PrimeVueKit\Installer\AuthScaffolder;
use PrimeVueKit\Installer\Concerns\InstallsComposerPackages;
use PrimeVueKit\Installer\DashboardPublisher;
use PrimeVueKit\Installer\Dependency;
use PrimeVueKit\Installer\DependencyChecker;
use PrimeVueKit\Installer\DependencySet;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\select;

#[AsCommand(name: 'primevuekit:auth')]
class AuthCommand extends Command
{
    use InstallsComposerPackages;

    /**
     * Dependencias del stack base sin las que la autenticación no puede funcionar.
     *
     * @var list<string>
     */
    private const REQUIRES = ['inertiajs/inertia-laravel', 'vue', '@inertiajs/vue3'];

    /**
     * @var string
     */
    protected $signature = 'primevuekit:auth
                    {--strategy= : fortify, kit o manual. Si se omite, se pregunta}
                    {--check : Diagnostica el estado y sale con 1 si falta algo. No modifica nada}
                    {--composer=global : Ruta absoluta al binario de Composer}
                    {--force : Sobrescribe los archivos ya generados}
                    {--no-dashboard : No copia el panel ni las páginas de perfil}';

    /**
     * @var string
     */
    protected $description = 'Instala la autenticación del kit, con OTP por correo y TOTP obligatorios';

    public function handle(): int
    {
        $basePath = $this->laravel->basePath();

        if (! $this->baseStackIsReady($basePath)) {
            $this->components->error(
                'Falta el stack de frontend (Inertia y Vue). Ejecuta antes `php artisan primevuekit:install`.'
            );

            return self::FAILURE;
        }

        if ($this->option('check')) {
            return $this->reportStatus($basePath);
        }

        $strategy = $this->resolveStrategy();

        if ($strategy === null) {
            $this->components->error('La opción --strategy admite sólo fortify, kit o manual.');

            return self::FAILURE;
        }

        $this->components->info('Autenticación — '.$strategy->label());
        $this->components->bulletList([
            'El segundo factor por correo (OTP) y el de aplicación (TOTP) se instalan siempre',
        ]);

        if ($strategy->usesFortify() && ! $this->installFortify()) {
            return self::FAILURE;
        }

        $this->scaffold($basePath, $strategy);
        $this->publishMigration();

        if ($this->option('no-dashboard') !== true) {
            $this->publishDashboard($basePath);
        }

        $this->summarize($strategy);

        return self::SUCCESS;
    }

    private function baseStackIsReady(string $basePath): bool
    {
        $required = array_values(array_filter(
            DependencySet::for(PrimeVueLine::Mit),
            fn (Dependency $dependency): bool => in_array($dependency->name, self::REQUIRES, true),
        ));

        foreach ((new DependencyChecker($basePath))->check($required) as $report) {
            if ($report->state === DependencyState::Missing) {
                return false;
            }
        }

        return true;
    }

    /**
     * Diagnóstico sin efectos secundarios: se mira sólo el sistema de archivos, para que
     * `--check` funcione igual con o sin base de datos disponible.
     */
    private function reportStatus(string $basePath): int
    {
        $user = @file_get_contents($basePath.'/app/Models/User.php');
        $appJs = @file_get_contents($basePath.'/resources/js/app.js');
        $appCss = @file_get_contents($basePath.'/resources/css/app.css');

        $checks = [
            'laravel/fortify' => is_dir($basePath.'/vendor/laravel/fortify'),
            'trait HasEmailOtp en User' => is_string($user) && str_contains($user, 'HasEmailOtp'),
            'segundo factor de app en User' => is_string($user)
                && (str_contains($user, 'HasTotp') || str_contains($user, 'TwoFactorAuthenticatable')),
            'MustVerifyEmail en User' => is_string($user) && str_contains($user, 'implements MustVerifyEmail'),
            'migración publicada' => $this->migrationIsPublished($basePath),
            'panel y perfil publicados' => is_file($basePath.'/resources/js/Pages/Dashboard.vue'),
            'páginas del kit en el resolver' => is_string($appJs) && str_contains($appJs, 'kitPages'),
            '@source del kit en app.css' => is_string($appCss) && str_contains($appCss, 'primevuekit/resources/js'),
        ];

        $this->components->info('Estado de la autenticación');

        foreach ($checks as $label => $done) {
            $this->components->twoColumnDetail($label, $done ? 'sí' : 'no');
        }

        // El marcador real de que la autenticación está instalada son los traits del User.
        if ($checks['trait HasEmailOtp en User'] && $checks['segundo factor de app en User']) {
            $this->components->info('La autenticación está instalada.');

            return self::SUCCESS;
        }

        $this->components->warn('Sin instalar. Ejecuta `php artisan primevuekit:auth` para elegir la estrategia.');

        return self::FAILURE;
    }

    private function resolveStrategy(): ?AuthStrategy
    {
        $option = $this->option('strategy');

        if (is_string($option) && $option !== '') {
            return AuthStrategy::tryFrom($option);
        }

        $options = [];

        foreach (AuthStrategy::cases() as $case) {
            $options[$case->value] = $case->label().' — '.$case->description();
        }

        $choice = select(
            label: '¿Qué backend de autenticación quieres instalar?',
            options: $options,
            default: AuthStrategy::Kit->value,
        );

        return AuthStrategy::tryFrom((string) $choice);
    }

    private function installFortify(): bool
    {
        if (is_dir($this->laravel->basePath('vendor/laravel/fortify'))) {
            $this->components->twoColumnDetail('laravel/fortify', 'ya instalado');
        } elseif (! $this->requireComposerPackages(['laravel/fortify:^1.39'])) {
            return false;
        }

        // En un subproceso: el provider de Fortify no está en el autoloader de este proceso.
        if (! $this->runArtisanInSubprocess('fortify:install')) {
            $this->components->warn(
                'No se pudo ejecutar `php artisan fortify:install`. Ejecútalo a mano antes de migrar.'
            );
        }

        return true;
    }

    private function scaffold(string $basePath, AuthStrategy $strategy): void
    {
        $outcomes = (new AuthScaffolder($basePath, $this->kitPagesPath($basePath)))->run($strategy);

        if ($strategy->publishesToApplication()) {
            $outcomes += (new AuthPublisher($basePath, dirname(__DIR__, 3)))
                ->publish($this->option('force') === true);
        }

        foreach ($outcomes as $file => $outcome) {
            $this->components->twoColumnDetail($file, $outcome->label());
        }
    }

    /**
     * El panel y el perfil se publican siempre en la aplicación: son de cada proyecto.
     */
    private function publishDashboard(string $basePath): void
    {
        $outcomes = (new DashboardPublisher($basePath))->publish($this->option('force') === true);

        foreach ($outcomes as $file => $outcome) {
            $this->components->twoColumnDetail($file, $outcome->label());
        }
    }

    private function publishMigration(): void
    {
        if ($this->migrationIsPublished($this->laravel->basePath())) {
            $this->components->twoColumnDetail('database/migrations', 'sin cambios');

            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'primevuekit-auth-migrations']);

        $this->components->twoColumnDetail('database/migrations', 'migración publicada');
    }

    private function migrationIsPublished(string $basePath): bool
    {
        $matches = glob($basePath.'/database/migrations/*create_primevuekit_auth_tables.php');

        return is_array($matches) && $matches !== [];
    }

    /**
     * Ruta de las páginas del paquete relativa a `resources/js` y `resources/css`.
     *
     * Se calcula en la instalación porque el paquete puede vivir en `packages/` (repositorio
     * path) o en `vendor/primevuekit/primevuekit` (instalación normal).
     */
    private function kitPagesPath(string $basePath): string
    {
        $packageRoot = str_replace('\\', '/', dirname(__DIR__, 3));
        $base = rtrim(str_replace('\\', '/', $basePath), '/').'/';

        $relative = str_starts_with($packageRoot, $base)
            ? substr($packageRoot, strlen($base))
            : 'vendor/primevuekit/primevuekit';

        return '../../'.$relative.'/resources/js/pages';
    }

    private function summarize(AuthStrategy $strategy): void
    {
        $this->newLine();
        $this->components->info('Autenticación instalada — '.$strategy->label());

        $steps = [
            'Ejecuta las migraciones: `php artisan migrate`',
            'Apunta el destino tras el login al panel: PRIMEVUEKIT_HOME=/dashboard',
            'Reinicia el servidor de Vite para que vea las páginas nuevas',
            'Los códigos OTP salen por cola: el worker `queue` debe estar corriendo',
            'En local los correos se leen en Mailpit: http://localhost:8025',
        ];

        if ($strategy->usesFortify()) {
            $steps[] = 'Activa `Features::twoFactorAuthentication()` en `config/fortify.php`';
        }

        $this->components->bulletList($steps);
    }
}
