<?php

declare(strict_types=1);

namespace PrimeVueKit\Enums;

/**
 * Línea de distribución de PrimeVue.
 *
 * La 4.x es la última publicada bajo licencia MIT. Desde la 5.x PrimeTek distribuye
 * PrimeVue bajo la licencia PrimeUI, que exige una clave de licencia.
 */
enum PrimeVueLine: int
{
    case Mit = 4;
    case PrimeUi = 5;

    public function requiresLicenseKey(): bool
    {
        return $this === self::PrimeUi;
    }

    public function label(): string
    {
        return match ($this) {
            self::Mit => 'PrimeVue 4.x (MIT)',
            self::PrimeUi => 'PrimeVue 5.x (licencia PrimeUI)',
        };
    }
}
