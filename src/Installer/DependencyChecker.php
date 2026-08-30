<?php

declare(strict_types=1);

namespace PrimeVueKit\Installer;

use PrimeVueKit\Enums\DependencyState;

/**
 * Determina el estado real de cada dependencia leyendo los manifiestos de la aplicación.
 *
 * Recibe la ruta base por constructor (en vez de usar `base_path()`) para poder ejecutarse
 * contra fixtures en las pruebas. No usa `Composer\InstalledVersions` a propósito: es
 * estático, no simulable, y leer los archivos mantiene la simetría con el lado npm.
 */
final class DependencyChecker
{
    public function __construct(private readonly string $basePath) {}

    /**
     * @param  list<Dependency>  $dependencies
     * @return list<DependencyReport>
     */
    public function check(array $dependencies): array
    {
        $composerJson = $this->json('composer.json');
        $composerLock = $this->json('composer.lock');
        $packageJson = $this->json('package.json');

        return array_map(
            fn (Dependency $dependency): DependencyReport => $dependency->isComposer()
                ? $this->checkComposer($dependency, $composerJson, $composerLock)
                : $this->checkNpm($dependency, $packageJson),
            $dependencies,
        );
    }

    /**
     * @param  array<string, mixed>  $composerJson
     * @param  array<string, mixed>  $composerLock
     */
    private function checkComposer(Dependency $dependency, array $composerJson, array $composerLock): DependencyReport
    {
        return $this->report(
            $dependency,
            $this->declaredConstraint($composerJson, ['require', 'require-dev'], $dependency->name),
            $this->lockedVersion($composerLock, $dependency->name),
            is_dir($this->path('vendor/'.$dependency->name)),
        );
    }

    /**
     * @param  array<string, mixed>  $packageJson
     */
    private function checkNpm(Dependency $dependency, array $packageJson): DependencyReport
    {
        $manifest = $this->json('node_modules/'.$dependency->name.'/package.json');
        $version = $manifest['version'] ?? null;
        $version = is_string($version) ? $version : null;

        return $this->report(
            $dependency,
            $this->declaredConstraint($packageJson, ['dependencies', 'devDependencies'], $dependency->name),
            $version,
            $version !== null,
        );
    }

    private function report(Dependency $dependency, ?string $declared, ?string $version, bool $onDisk): DependencyReport
    {
        if ($declared === null && ! $onDisk) {
            return new DependencyReport($dependency, DependencyState::Missing, null);
        }

        // Cuando node_modules vive en un volumen de Docker no hay versión instalada que leer,
        // así que se cae a la restricción declarada para poder detectar majors mezcladas.
        $detected = $version ?? ($declared !== null ? self::versionFromConstraint($declared) : null);

        if ($detected !== null && ! $dependency->matchesSeries($detected)) {
            return new DependencyReport($dependency, DependencyState::SeriesMismatch, $detected);
        }

        return new DependencyReport(
            $dependency,
            $onDisk ? DependencyState::Installed : DependencyState::Declared,
            $detected,
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $sections
     */
    private function declaredConstraint(array $manifest, array $sections, string $name): ?string
    {
        foreach ($sections as $section) {
            $entries = $manifest[$section] ?? null;

            if (is_array($entries) && isset($entries[$name]) && is_string($entries[$name])) {
                return $entries[$name];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $composerLock
     */
    private function lockedVersion(array $composerLock, string $name): ?string
    {
        foreach (['packages', 'packages-dev'] as $section) {
            $packages = $composerLock[$section] ?? null;

            if (! is_array($packages)) {
                continue;
            }

            foreach ($packages as $package) {
                if (! is_array($package) || ($package['name'] ?? null) !== $name) {
                    continue;
                }

                $version = $package['version'] ?? null;

                if (is_string($version)) {
                    return $version;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $relativePath): array
    {
        $path = $this->path($relativePath);

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function path(string $relativePath): string
    {
        return rtrim($this->basePath, '/\\').DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private static function versionFromConstraint(string $constraint): ?string
    {
        return preg_match('/\d+(?:\.\d+)*/', $constraint, $matches) === 1 ? $matches[0] : null;
    }
}
