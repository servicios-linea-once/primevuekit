<?php

declare(strict_types=1);

namespace PrimeVueKit\Installer;

use PrimeVueKit\Enums\PrimeVueLine;

/**
 * Catálogo único de versiones del stack. Es el sitio donde se sube una versión.
 *
 * Todas las restricciones se comprobaron contra registry.npmjs.org y packagist.org
 * el 2026-08-28. Ojo con dos nombres fáciles de equivocar:
 *
 * - El paquete Composer de Ziggy es `tightenco/ziggy` (`tighten/ziggy` no existe).
 * - `npm install primevue` resuelve a la 5.x, que ya no es MIT. Hay que fijar la versión.
 */
final class DependencySet
{
    /**
     * Series coherentes de la tripleta de PrimeVue. Mezclarlas rompe el modo styled
     * porque `primevue` y `@primeuix/themes` deben compartir `@primeuix/styled`.
     *
     * @var array<int, array<string, string>>
     */
    private const PRIME_SERIES = [
        4 => ['primevue' => '4', '@primeuix/themes' => '2', 'primeicons' => '7'],
        5 => ['primevue' => '5', '@primeuix/themes' => '3', 'primeicons' => '8'],
    ];

    /**
     * @return list<Dependency>
     */
    public static function for(PrimeVueLine $line): array
    {
        return [...self::composer(), ...self::npm($line)];
    }

    /**
     * @return list<Dependency>
     */
    public static function composer(): array
    {
        return [
            Dependency::composer('inertiajs/inertia-laravel', '^3.3', '3'),
            Dependency::composer('tightenco/ziggy', '^2.6', '2'),
        ];
    }

    /**
     * @return list<Dependency>
     */
    public static function npm(PrimeVueLine $line): array
    {
        return [
            Dependency::npm('vue', '^3.5.42', '3'),
            Dependency::npm('@inertiajs/vue3', '^3.7.0', '3'),
            Dependency::npm('ziggy-js', '^2.6.4', '2'),
            ...self::primeVue($line),
            Dependency::npm('@vitejs/plugin-vue', '^6.0.8', '6', dev: true),
            Dependency::npm('tailwindcss-primeui', '^0.6.1', '0.6', dev: true),
        ];
    }

    /**
     * @return list<Dependency>
     */
    public static function primeVue(PrimeVueLine $line): array
    {
        return match ($line) {
            PrimeVueLine::Mit => [
                Dependency::npm('primevue', '^4.5.5', '4'),
                Dependency::npm('@primeuix/themes', '^2.0.3', '2'),
                Dependency::npm('primeicons', '^7.0.0', '7'),
            ],
            PrimeVueLine::PrimeUi => [
                Dependency::npm('primevue', '^5.0.1', '5'),
                Dependency::npm('@primeuix/themes', '^3.0.0', '3'),
                Dependency::npm('primeicons', '^8.0.0', '8'),
            ],
        };
    }

    /**
     * Series esperadas para la tripleta de PrimeVue de una línea dada.
     *
     * @return array<string, string>
     */
    public static function primeSeriesFor(PrimeVueLine $line): array
    {
        return self::PRIME_SERIES[$line->value];
    }

    /**
     * Línea a la que pertenece una serie concreta de `primevue`.
     */
    public static function lineFromPrimeVueSeries(string $series): ?PrimeVueLine
    {
        foreach (self::PRIME_SERIES as $value => $series_) {
            if ($series_['primevue'] === $series) {
                return PrimeVueLine::from($value);
            }
        }

        return null;
    }
}
