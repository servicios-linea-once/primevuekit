<?php

declare(strict_types=1);

namespace PrimeVueKit\Installer\Concerns;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

use function Illuminate\Support\artisan_binary;
use function Illuminate\Support\php_binary;

/**
 * Instalación de paquetes de Composer desde un comando, con el mismo patrón que
 * `Illuminate\Foundation\Console\InteractsWithComposerPackages`: si el proceso falla no
 * se aborta, se imprime el comando para ejecutarlo a mano.
 *
 * @phpstan-require-extends Command
 */
trait InstallsComposerPackages
{
    /**
     * @param  list<string>  $packages
     */
    protected function requireComposerPackages(array $packages): bool
    {
        $command = [...$this->composerCommand(), ...$packages];

        $this->components->info('Instalando con Composer: '.implode(' ', $packages));

        $failed = Process::path($this->laravel->basePath())
            ->env(['COMPOSER_MEMORY_LIMIT' => '-1'])
            ->forever()
            ->run($command, function (string $type, string $output): void {
                $this->output->write($output);
            })
            ->failed();

        if ($failed) {
            $this->components->warn(
                'Falló la instalación con Composer. Ejecútala manualmente: '.implode(' ', $command)
            );
        }

        return ! $failed;
    }

    /**
     * Lanza un comando de Artisan en un subproceso.
     *
     * Es imprescindible cuando el comando pertenece a un paquete que Composer acaba de
     * instalar en esta misma ejecución: su service provider no está registrado en el
     * kernel ya arrancado ni sus clases en el autoloader.
     */
    protected function runArtisanInSubprocess(string ...$arguments): bool
    {
        return Process::path($this->laravel->basePath())
            ->forever()
            ->run([php_binary(), artisan_binary(), ...$arguments, '--no-interaction'])
            ->successful();
    }

    /**
     * @return list<string>
     */
    private function composerCommand(): array
    {
        $composer = $this->option('composer');
        $composer = is_string($composer) ? $composer : 'global';

        return $composer === 'global'
            ? ['composer', 'require']
            : [php_binary(), $composer, 'require'];
    }
}
