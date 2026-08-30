<?php

declare(strict_types=1);

namespace PrimeVueKit\Enums;

/**
 * Estrategias de autenticación que ofrece `primevuekit:auth`.
 *
 * No hay opción de Breeze: `breeze:install vue` fija Inertia 2 y Tailwind 3 y sobrescribe
 * app.js, vite.config.js, app.css, routes/web.php y la vista raíz, así que destruiría el
 * stack del kit. Su propio README lo declara para «Laravel 11.x and prior».
 */
enum AuthStrategy: string
{
    /** Backend delegado en laravel/fortify; el kit añade el OTP por correo y la UI. */
    case Fortify = 'fortify';

    /** Rutas, controladores y páginas del propio paquete. */
    case Kit = 'kit';

    /** Como Kit, pero publicando todo en la aplicación para editarlo. */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Fortify => 'Fortify',
            self::Kit => 'Preset del kit',
            self::Manual => 'Manual',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Fortify => 'laravel/fortify se encarga de login, registro, reset, verificación y TOTP; el kit añade el OTP por correo y las páginas.',
            self::Kit => 'Rutas, controladores y páginas viven en el paquete y se actualizan con él. Sin dependencias externas.',
            self::Manual => 'El mismo código que el preset del kit, pero publicado en tu aplicación para que lo edites.',
        };
    }

    public function usesFortify(): bool
    {
        return $this === self::Fortify;
    }

    public function publishesToApplication(): bool
    {
        return $this === self::Manual;
    }
}
