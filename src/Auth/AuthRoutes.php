<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth;

use Illuminate\Support\Facades\Route;

/**
 * Registra las rutas de autenticación del preset `kit`.
 *
 * El comando `primevuekit:auth` añade una llamada a `AuthRoutes::register()` en el
 * `routes/web.php` de la aplicación. Se hace así, y no cargando las rutas desde el
 * service provider, para que una aplicación que sólo quiera los componentes de PrimeVue
 * no acabe con un `/login` que no ha pedido, y para que la ruta del paquete no dependa
 * de si vive en `packages/` o en `vendor/`.
 */
final class AuthRoutes
{
    public static function register(): void
    {
        // Sin envolver en el grupo `web`: se invoca desde routes/web.php, que ya lo aplica.
        Route::group([], __DIR__.'/../../routes/auth.php');
    }
}
