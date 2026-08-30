<?php

declare(strict_types=1);

namespace PrimeVueKit\Enums;

enum DependencyState: string
{
    /** No aparece ni declarada ni en disco. */
    case Missing = 'missing';

    /** Declarada en composer.json / package.json pero todavía no presente en disco. */
    case Declared = 'declared';

    /** Declarada y presente en vendor/ o node_modules/. */
    case Installed = 'installed';

    /** Presente, pero de una serie de versiones distinta a la esperada. */
    case SeriesMismatch = 'series_mismatch';

    public function needsInstall(): bool
    {
        return $this === self::Missing;
    }

    public function isProblem(): bool
    {
        return $this === self::Missing || $this === self::SeriesMismatch;
    }

    public function label(): string
    {
        return match ($this) {
            self::Missing => 'falta',
            self::Declared => 'declarada (sin instalar)',
            self::Installed => 'instalada',
            self::SeriesMismatch => 'versión incompatible',
        };
    }
}
