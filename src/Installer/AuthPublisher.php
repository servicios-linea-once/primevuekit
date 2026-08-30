<?php

declare(strict_types=1);

namespace PrimeVueKit\Installer;

use PrimeVueKit\Enums\ScaffoldOutcome;
use PrimeVueKit\Installer\Concerns\EditsFiles;

/**
 * Copia los controladores, Form Requests, rutas y páginas del paquete a la aplicación
 * para la estrategia `manual`, reescribiendo namespaces e imports.
 *
 * El núcleo (servicios de TOTP y OTP, traits, middleware) se queda en el paquete: es lo
 * que conviene seguir actualizando, y no es lo que un proyecto quiere editar.
 */
final class AuthPublisher
{
    use EditsFiles;

    /**
     * namespace o import del paquete => destino en la aplicación.
     */
    private const REWRITES = [
        'namespace PrimeVueKit\Auth\Http\Controllers;' => 'namespace App\Http\Controllers\Auth;',
        'namespace PrimeVueKit\Auth\Http\Requests;' => 'namespace App\Http\Requests\Auth;',
        'use PrimeVueKit\Auth\Http\Controllers\\' => 'use App\Http\Controllers\Auth\\',
        'use PrimeVueKit\Auth\Http\Requests\\' => 'use App\Http\Requests\Auth\\',
    ];

    public function __construct(
        private readonly string $basePath,
        private readonly string $packagePath,
    ) {}

    /**
     * @return array<string, ScaffoldOutcome>
     */
    public function publish(bool $force = false): array
    {
        return [
            'app/Http/Controllers/Auth' => $this->copyPhp('src/Auth/Http/Controllers', 'app/Http/Controllers/Auth', $force),
            'app/Http/Requests/Auth' => $this->copyPhp('src/Auth/Http/Requests', 'app/Http/Requests/Auth', $force),
            'routes/auth.php' => $this->copyRoutes($force),
            'resources/js/Layouts/AuthCard.vue' => $this->copyLayout($force),
            'resources/js/Components' => $this->copyVue('resources/js/components', 'resources/js/Components', $force),
            'resources/js/Pages/Auth' => $this->copyVue('resources/js/pages/Auth', 'resources/js/Pages/Auth', $force),
        ];
    }

    protected function basePath(): string
    {
        return $this->basePath;
    }

    private function copyPhp(string $from, string $to, bool $force): ScaffoldOutcome
    {
        $files = glob($this->packagePath.'/'.$from.'/*.php');

        if (! is_array($files) || $files === []) {
            return ScaffoldOutcome::Manual;
        }

        $outcome = ScaffoldOutcome::Skipped;

        foreach ($files as $file) {
            $target = $to.'/'.basename($file);

            if (is_file($this->path($target)) && ! $force) {
                continue;
            }

            $contents = file_get_contents($file);

            if ($contents === false) {
                return ScaffoldOutcome::Manual;
            }

            $this->put($target, $this->rewrite($contents), "\n");
            $outcome = ScaffoldOutcome::Applied;
        }

        return $outcome;
    }

    private function copyRoutes(bool $force): ScaffoldOutcome
    {
        if (is_file($this->path('routes/auth.php')) && ! $force) {
            return ScaffoldOutcome::Skipped;
        }

        $contents = file_get_contents($this->packagePath.'/routes/auth.php');

        if ($contents === false) {
            return ScaffoldOutcome::Manual;
        }

        return $this->put('routes/auth.php', $this->rewrite($contents), "\n");
    }

    private function copyLayout(bool $force): ScaffoldOutcome
    {
        if (is_file($this->path('resources/js/Layouts/AuthCard.vue')) && ! $force) {
            return ScaffoldOutcome::Skipped;
        }

        $contents = file_get_contents($this->packagePath.'/resources/js/layouts/AuthCard.vue');

        if ($contents === false) {
            return ScaffoldOutcome::Manual;
        }

        return $this->put('resources/js/Layouts/AuthCard.vue', $this->rewriteVuePaths($contents), "\n");
    }

    private function copyVue(string $from, string $to, bool $force): ScaffoldOutcome
    {
        $files = glob($this->packagePath.'/'.$from.'/*.vue');

        if (! is_array($files) || $files === []) {
            return ScaffoldOutcome::Manual;
        }

        $outcome = ScaffoldOutcome::Skipped;

        foreach ($files as $file) {
            $target = $to.'/'.basename($file);

            if (is_file($this->path($target)) && ! $force) {
                continue;
            }

            $contents = file_get_contents($file);

            if ($contents === false) {
                return ScaffoldOutcome::Manual;
            }

            $this->put($target, $this->rewriteVuePaths($contents), "\n");
            $outcome = ScaffoldOutcome::Applied;
        }

        return $outcome;
    }

    /**
     * En la aplicación los directorios de Vue van en mayúscula (convención de Laravel) y en
     * el paquete en minúscula, así que hay que reescribir los imports relativos.
     */
    private function rewriteVuePaths(string $contents): string
    {
        return str_replace(['layouts/', 'components/'], ['Layouts/', 'Components/'], $contents);
    }

    private function rewrite(string $contents): string
    {
        return str_replace(array_keys(self::REWRITES), array_values(self::REWRITES), $this->normalize($contents));
    }
}
