<?php

declare(strict_types=1);

namespace PrimeVueKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use PrimeVueKit\Enums\DependencyState;
use PrimeVueKit\Enums\PrimeVueLine;
use PrimeVueKit\Installer\ApplicationScaffolder;
use PrimeVueKit\Installer\Concerns\InstallsComposerPackages;
use PrimeVueKit\Installer\Dependency;
use PrimeVueKit\Installer\DependencyChecker;
use PrimeVueKit\Installer\DependencyReport;
use PrimeVueKit\Installer\DependencySet;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'primevuekit:install')]
class InstallCommand extends Command
{
    use InstallsComposerPackages;

    /**
     * @var string
     */
    protected $signature = 'primevuekit:install
                    {--check : Sólo diagnostica el stack; no modifica nada y sale con 1 si falta algo}
                    {--primevue=4 : Línea de PrimeVue: 4 (MIT) o 5 (licencia PrimeUI)}
                    {--composer=global : Ruta absoluta al binario de Composer}
                    {--force : Sobrescribe los archivos de wiring existentes}
                    {--no-scaffold : Instala dependencias sin tocar archivos de la aplicación}
                    {--no-demo : Omite la ruta y la página Inertia de demostración}';

    /**
     * @var string
     */
    protected $description = 'Verifica e instala Vue, PrimeVue, Inertia y Ziggy en la aplicación';

    public function handle(): int
    {
        $line = PrimeVueLine::tryFrom((int) $this->option('primevue'));

        if ($line === null) {
            $this->components->error('La opción --primevue admite sólo 4 (MIT) o 5 (licencia PrimeUI).');

            return self::FAILURE;
        }

        if ($line->requiresLicenseKey()) {
            $this->warnAboutPrimeUiLicense();
        }

        $basePath = $this->laravel->basePath();
        $reports = (new DependencyChecker($basePath))->check(DependencySet::for($line));

        $this->components->info('Estado del stack — '.$line->label());

        foreach ($reports as $report) {
            $this->components->twoColumnDetail($report->name(), $report->describe());
        }

        $incoherence = $this->primeIncoherence($reports);

        if ($incoherence !== null) {
            $this->components->error($incoherence);

            return self::FAILURE;
        }

        if ($this->option('check')) {
            return $this->reportCheck($reports);
        }

        if ($this->hasMismatches($reports)) {
            $this->components->error(
                'Hay dependencias en una serie de versiones incompatible. Resuélvelas a mano '
                .'(o ajusta --primevue) antes de instalar.'
            );

            return self::FAILURE;
        }

        if ($line->requiresLicenseKey() && ! $this->confirm('¿Continuar con la línea 5.x bajo licencia PrimeUI?', false)) {
            return self::FAILURE;
        }

        $this->installComposerDependencies($reports);
        $this->installNodeDependencies($reports);

        $middlewareAvailable = $this->publishInertiaMiddleware();

        if ($this->option('no-scaffold') !== true) {
            $this->scaffold($basePath, $middlewareAvailable);
        }

        $this->summarize();

        return self::SUCCESS;
    }

    /**
     * @param  list<DependencyReport>  $reports
     */
    private function reportCheck(array $reports): int
    {
        $problems = array_filter($reports, fn (DependencyReport $report): bool => $report->state->isProblem());

        if ($problems === []) {
            $this->components->info('El stack está completo.');

            return self::SUCCESS;
        }

        $this->components->warn(sprintf(
            '%d dependencia(s) sin resolver. Ejecuta `php artisan primevuekit:install` para instalarlas.',
            count($problems),
        ));

        return self::FAILURE;
    }

    /**
     * @param  list<DependencyReport>  $reports
     */
    private function hasMismatches(array $reports): bool
    {
        foreach ($reports as $report) {
            if ($report->state === DependencyState::SeriesMismatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detecta la mezcla de líneas de PrimeVue, que rompe el modo styled en silencio.
     *
     * @param  list<DependencyReport>  $reports
     */
    private function primeIncoherence(array $reports): ?string
    {
        $majors = [];

        foreach ($reports as $report) {
            $major = $report->detectedMajor();

            if ($major !== null && in_array($report->name(), ['primevue', '@primeuix/themes', 'primeicons'], true)) {
                $majors[$report->name()] = $major;
            }
        }

        $primevue = $majors['primevue'] ?? null;

        if ($primevue === null) {
            return null;
        }

        $line = DependencySet::lineFromPrimeVueSeries($primevue);

        if ($line === null) {
            return sprintf(
                'primevue %s.x no está soportado por el kit: usa la línea 4.x (MIT) o 5.x (PrimeUI).',
                $primevue,
            );
        }

        foreach (DependencySet::primeSeriesFor($line) as $name => $series) {
            $detected = $majors[$name] ?? null;

            if ($detected !== null && $detected !== $series) {
                return sprintf(
                    'Líneas de PrimeVue mezcladas: primevue %s.x requiere %s %s.x, pero hay %s.x. '
                    .'No comparten @primeuix/styled, así que el modo styled se rompe.',
                    $primevue, $name, $series, $detected,
                );
            }
        }

        return null;
    }

    private function warnAboutPrimeUiLicense(): void
    {
        $this->components->warn(implode(PHP_EOL, [
            'PrimeVue 5.x no es MIT: se distribuye bajo la licencia PrimeUI.',
            '· Exige una clave de licencia que este comando NO configura.',
            '· El tier Community es gratuito sólo por debajo de 1M USD de ingresos, 5 desarrolladores,',
            '  10 empleados y 3M USD de financiación, con rotación de clave cada 12 meses.',
            '· El tier Commercial cuesta 599 USD por desarrollador.',
            '· La licencia prohíbe redistribuirlo como librería de componentes sin acuerdo OEM.',
            'La última línea MIT es la 4.x (--primevue=4).',
        ]));
    }

    /**
     * @param  list<DependencyReport>  $reports
     */
    private function installComposerDependencies(array $reports): void
    {
        $packages = $this->pendingArguments($reports, fn (Dependency $dependency): bool => $dependency->isComposer());

        if ($packages === []) {
            $this->components->twoColumnDetail('Paquetes de Composer', 'sin cambios');

            return;
        }

        $this->requireComposerPackages($packages);
    }

    /**
     * @param  list<DependencyReport>  $reports
     */
    private function installNodeDependencies(array $reports): void
    {
        $production = $this->pendingArguments(
            $reports,
            fn (Dependency $dependency): bool => $dependency->isNpm() && ! $dependency->dev,
        );

        $development = $this->pendingArguments(
            $reports,
            fn (Dependency $dependency): bool => $dependency->isNpm() && $dependency->dev,
        );

        if ($production === [] && $development === []) {
            $this->components->twoColumnDetail('Paquetes de Node', 'sin cambios');

            return;
        }

        $manager = $this->nodePackageManager();
        $commands = [];

        if ($production !== []) {
            $commands[] = $this->nodeCommand($manager, $production);
        }

        if ($development !== []) {
            $commands[] = $this->nodeCommand($manager, $development, dev: true);
        }

        if (! $this->nodeBinaryAvailable($manager['binary'])) {
            $this->components->warn(sprintf(
                'No se encontró el binario [%s] en este entorno (el contenedor app no trae Node). '
                .'Ejecuta la instalación en el servicio vite:',
                $manager['binary'],
            ));

            $this->components->bulletList(array_map(
                fn (string $command): string => 'docker compose run --rm vite '.$command,
                $commands,
            ));

            return;
        }

        $this->components->info('Instalando dependencias de Node.');

        $process = Process::path($this->laravel->basePath())
            ->forever()
            ->command(implode(' && ', $commands));

        if (PHP_OS_FAMILY !== 'Windows') {
            $process->tty(true);
        }

        if ($process->run()->failed()) {
            $this->components->warn(
                'Falló la instalación de Node. Ejecútala manualmente: '.implode(' && ', $commands)
            );
        }
    }

    /**
     * @param  array{binary: string, add: string, dev: string, ignoreScripts: string}  $manager
     * @param  list<string>  $packages
     */
    private function nodeCommand(array $manager, array $packages, bool $dev = false): string
    {
        $parts = [$manager['binary'], $manager['add']];

        if ($dev) {
            $parts[] = $manager['dev'];
        }

        $parts = [...$parts, ...$packages];

        if ($manager['ignoreScripts'] !== '') {
            $parts[] = $manager['ignoreScripts'];
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{binary: string, add: string, dev: string, ignoreScripts: string}
     */
    private function nodePackageManager(): array
    {
        $basePath = $this->laravel->basePath();

        return match (true) {
            is_file($basePath.'/pnpm-lock.yaml') => ['binary' => 'pnpm', 'add' => 'add', 'dev' => '--save-dev', 'ignoreScripts' => '--ignore-scripts'],
            is_file($basePath.'/yarn.lock') => ['binary' => 'yarn', 'add' => 'add', 'dev' => '--dev', 'ignoreScripts' => '--ignore-scripts'],
            is_file($basePath.'/bun.lock'), is_file($basePath.'/bun.lockb') => ['binary' => 'bun', 'add' => 'add', 'dev' => '--dev', 'ignoreScripts' => ''],
            default => ['binary' => 'npm', 'add' => 'install', 'dev' => '--save-dev', 'ignoreScripts' => '--ignore-scripts'],
        };
    }

    private function nodeBinaryAvailable(string $binary): bool
    {
        return Process::path($this->laravel->basePath())
            ->quietly()
            ->run($binary.' --version')
            ->successful();
    }

    /**
     * @param  list<DependencyReport>  $reports
     * @param  callable(Dependency): bool  $filter
     * @return list<string>
     */
    private function pendingArguments(array $reports, callable $filter): array
    {
        $arguments = [];

        foreach ($reports as $report) {
            if ($report->state->needsInstall() && $filter($report->dependency)) {
                $arguments[] = $report->dependency->installArgument();
            }
        }

        return $arguments;
    }

    /**
     * Genera app/Http/Middleware/HandleInertiaRequests.php si todavía no existe.
     *
     * Se lanza en un subproceso porque el service provider de Inertia que acaba de instalar
     * Composer no está en el autoloader del proceso actual.
     */
    private function publishInertiaMiddleware(): bool
    {
        $path = $this->laravel->basePath('app/Http/Middleware/HandleInertiaRequests.php');

        if (is_file($path)) {
            $this->components->twoColumnDetail('app/Http/Middleware/HandleInertiaRequests.php', 'sin cambios');

            return true;
        }

        $this->runArtisanInSubprocess('inertia:middleware');

        if (! is_file($path)) {
            $this->components->warn(
                'No se generó HandleInertiaRequests. Ejecuta `php artisan inertia:middleware` y añade '
                .'$middleware->web(append: [HandleInertiaRequests::class]) en bootstrap/app.php.'
            );

            return false;
        }

        $this->components->twoColumnDetail('app/Http/Middleware/HandleInertiaRequests.php', 'creado');

        return true;
    }

    private function scaffold(string $basePath, bool $withMiddleware): void
    {
        $outcomes = (new ApplicationScaffolder($basePath))->run(
            force: $this->option('force') === true,
            withDemo: $this->option('no-demo') !== true,
            withMiddleware: $withMiddleware,
        );

        $this->components->info('Wiring de la aplicación');

        foreach ($outcomes as $file => $outcome) {
            $this->components->twoColumnDetail($file, $outcome->label());
        }
    }

    private function summarize(): void
    {
        $this->newLine();
        $this->components->info('PrimeVueKit instalado.');
        $this->components->bulletList([
            'Compila los assets: `docker compose run --rm vite npm run build` o `npm run dev`.',
            'Abre http://localhost:8000/primevuekit para ver la página de demostración.',
            'Vuelve a ejecutar `php artisan primevuekit:install --check` para confirmar el estado.',
        ]);
    }
}
