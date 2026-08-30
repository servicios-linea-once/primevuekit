<?php

declare(strict_types=1);

namespace PrimeVueKit\Enums;

enum ScaffoldOutcome: string
{
    /** El archivo se creó o se modificó. */
    case Applied = 'applied';

    /** Ya estaba hecho, no se tocó nada. */
    case Skipped = 'skipped';

    /** El archivo no encaja con el patrón esperado: requiere intervención manual. */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'actualizado',
            self::Skipped => 'sin cambios',
            self::Manual => 'requiere ajuste manual',
        };
    }
}
