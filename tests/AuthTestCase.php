<?php

declare(strict_types=1);

namespace PrimeVueKit\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Inertia\ServiceProvider as InertiaServiceProvider;
use PrimeVueKit\Auth\AuthRoutes;
use PrimeVueKit\Tests\Fixtures\AuthUser;

/**
 * Caso base para las pruebas que necesitan base de datos: SQLite en memoria con las
 * migraciones de Laravel más la del paquete, y las rutas de autenticación registradas.
 */
abstract class AuthTestCase extends TestCase
{
    /**
     * Inertia hace falta explícitamente: registra el macro `assertInertia` en TestResponse
     * y los componentes Blade de la vista raíz.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), InertiaServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // AES-256-CBC exige exactamente 32 bytes de clave.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_pad('primevuekit-testing', 32, '.')));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Acelera los Hash::make de los códigos OTP en la suite.
        $app['config']->set('hashing.bcrypt.rounds', 4);

        // El modelo de usuario del paquete lleva los traits de segundo factor.
        $app['config']->set('auth.providers.users.model', AuthUser::class);

        // Vista raíz mínima para que Inertia pueda renderizar.
        $app['view']->addLocation(__DIR__.'/Fixtures/views');

        // Las páginas viven en el paquete: apuntando aquí, `assertInertia` comprueba de
        // verdad que el archivo .vue del componente existe.
        $app['config']->set('inertia.pages.paths', [realpath(__DIR__.'/../resources/js/pages')]);
        $app['config']->set('inertia.pages.extensions', ['vue']);
    }

    /**
     * En la aplicación las rutas se enganchan desde `routes/web.php`, que ya aplica el
     * grupo `web`. Aquí hay que aplicarlo a mano para tener sesión y CSRF.
     *
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function (): void {
            AuthRoutes::register();
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
