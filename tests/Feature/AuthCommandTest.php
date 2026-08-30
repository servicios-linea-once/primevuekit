<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use PrimeVueKit\Tests\Fixtures\ApplicationFixture;

/**
 * Fixture con el stack de frontend ya instalado, que es el requisito de `primevuekit:auth`.
 *
 * @return array<string, string>
 */
function appWithStack(): array
{
    return ApplicationFixture::withStackDeclared() + [
        'app/Models/User.php' => <<<'PHP'
            <?php

            namespace App\Models;

            // use Illuminate\Contracts\Auth\MustVerifyEmail;
            use Illuminate\Database\Eloquent\Factories\HasFactory;
            use Illuminate\Foundation\Auth\User as Authenticatable;
            use Illuminate\Notifications\Notifiable;

            class User extends Authenticatable
            {
                use HasFactory, Notifiable;
            }
            PHP,
        'resources/js/app.js' => <<<'JS'
            import { createInertiaApp } from '@inertiajs/vue3';
            import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

            createInertiaApp({
                resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
            });
            JS,
    ];
}

beforeEach(function (): void {
    Process::fake();
    Process::preventStrayProcesses();
});

it('registra el comando en Artisan', function (): void {
    expect(Artisan::all())->toHaveKey('primevuekit:auth');
});

it('exige el stack de frontend antes de instalar la autenticación', function (): void {
    $this->app->setBasePath(ApplicationFixture::create());

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])
        ->expectsOutputToContain('primevuekit:install')
        ->assertExitCode(1);

    Process::assertNothingRan();
});

it('rechaza una estrategia desconocida', function (): void {
    $this->app->setBasePath(ApplicationFixture::create(appWithStack()));

    $this->artisan('primevuekit:auth', ['--strategy' => 'breeze'])->assertExitCode(1);

    Process::assertNothingRan();
});

it('--check sale con 1 mientras la autenticación no está instalada', function (): void {
    $this->app->setBasePath(ApplicationFixture::create(appWithStack()));

    $this->artisan('primevuekit:auth', ['--check' => true])->assertExitCode(1);

    Process::assertNothingRan();
});

it('el preset del kit no instala paquetes de Composer', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])->assertExitCode(0);

    Process::assertDidntRun(fn (object $process): bool => str_contains(
        is_array($process->command) ? implode(' ', $process->command) : (string) $process->command,
        'composer require',
    ));

    $user = (string) file_get_contents($basePath.'/app/Models/User.php');

    expect($user)
        ->toContain('use PrimeVueKit\Auth\Concerns\HasEmailOtp;')
        ->toContain('use PrimeVueKit\Auth\Concerns\HasTotp;')
        ->toContain('use PrimeVueKit\Auth\Contracts\SupportsEmailOtp;')
        ->toContain('use PrimeVueKit\Auth\Contracts\SupportsTotp;')
        ->toContain('implements MustVerifyEmail, SupportsEmailOtp, SupportsTotp')
        ->toContain('use HasEmailOtp, HasFactory, HasTotp, Notifiable;');

    expect(file_get_contents($basePath.'/resources/js/app.js'))->toContain('kitPages');
    expect(file_get_contents($basePath.'/resources/css/app.css'))->toContain('primevuekit/resources/js');
});

it('--check sale con 0 después de instalar', function (): void {
    $this->app->setBasePath(ApplicationFixture::create(appWithStack()));

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])->assertExitCode(0);
    $this->artisan('primevuekit:auth', ['--check' => true])->assertExitCode(0);
});

it('es idempotente: una segunda pasada no duplica los traits', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])->assertExitCode(0);
    $first = (string) file_get_contents($basePath.'/app/Models/User.php');

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])->assertExitCode(0);

    expect(file_get_contents($basePath.'/app/Models/User.php'))->toBe($first)
        ->and(substr_count($first, 'HasEmailOtp'))->toBe(2);
});

it('la estrategia fortify instala el paquete y usa su trait de dos factores', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'fortify'])->assertExitCode(0);

    Process::assertRan(fn (object $process): bool => str_contains(
        is_array($process->command) ? implode(' ', $process->command) : (string) $process->command,
        'composer require laravel/fortify:^1.39',
    ));

    Process::assertRan(fn (object $process): bool => str_contains(
        is_array($process->command) ? implode(' ', $process->command) : (string) $process->command,
        'fortify:install',
    ));

    $user = (string) file_get_contents($basePath.'/app/Models/User.php');

    expect($user)->toContain('use Laravel\Fortify\TwoFactorAuthenticatable;');
    expect($user)->not->toContain('HasTotp');
});

it('el modo manual no toca el resolver ni el css', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'manual'])->assertExitCode(0);

    expect((string) file_get_contents($basePath.'/resources/js/app.js'))->not->toContain('kitPages');
    expect((string) file_get_contents($basePath.'/resources/css/app.css'))->not->toContain('primevuekit/resources/js');
    expect((string) file_get_contents($basePath.'/app/Models/User.php'))->toContain('HasTotp');
});

it('el preset del kit engancha las rutas desde routes/web.php', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])->assertExitCode(0);

    expect((string) file_get_contents($basePath.'/routes/web.php'))
        ->toContain('\PrimeVueKit\Auth\AuthRoutes::register();');
});

it('el modo manual publica controladores, requests, rutas y páginas con los namespaces reescritos', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'manual'])->assertExitCode(0);

    $controller = (string) file_get_contents($basePath.'/app/Http/Controllers/Auth/AuthenticatedSessionController.php');

    expect($controller)
        ->toContain('namespace App\Http\Controllers\Auth;')
        ->toContain('use App\Http\Requests\Auth\LoginRequest;')
        // El núcleo se queda en el paquete: es lo que conviene seguir actualizando.
        ->toContain('use PrimeVueKit\Auth\ChallengeSession;');

    expect((string) file_get_contents($basePath.'/app/Http/Requests/Auth/LoginRequest.php'))
        ->toContain('namespace App\Http\Requests\Auth;');

    expect((string) file_get_contents($basePath.'/routes/auth.php'))
        ->toContain('use App\Http\Controllers\Auth\AuthenticatedSessionController;');

    expect((string) file_get_contents($basePath.'/routes/web.php'))
        ->toContain("require __DIR__.'/auth.php';");

    expect((string) file_get_contents($basePath.'/resources/js/Pages/Auth/Login.vue'))
        ->toContain('../../Layouts/AuthCard.vue')
        ->toContain('../../Components/AuthSubmit.vue');

    expect((string) file_get_contents($basePath.'/resources/js/Layouts/AuthCard.vue'))
        ->toContain('../Components/AuthIllustration.vue');

    expect(is_file($basePath.'/resources/js/Layouts/AuthCard.vue'))->toBeTrue();
    expect(is_file($basePath.'/resources/js/Components/AuthIllustration.vue'))->toBeTrue();
    expect(is_file($basePath.'/resources/js/Components/AuthSubmit.vue'))->toBeTrue();
});

it('el modo manual es idempotente', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'manual'])->assertExitCode(0);
    $first = ApplicationFixture::snapshot($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'manual'])->assertExitCode(0);

    expect(ApplicationFixture::snapshot($basePath))->toBe($first);
});

it('no engancha las rutas dos veces si ya está la otra estrategia', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])->assertExitCode(0);

    // Cambiar de estrategia no debe dejar las rutas registradas por dos vías.
    $this->artisan('primevuekit:auth', ['--strategy' => 'manual'])
        ->expectsOutputToContain('routes/web.php')
        ->assertExitCode(0);

    $web = (string) file_get_contents($basePath.'/routes/web.php');

    expect($web)->toContain('\PrimeVueKit\Auth\AuthRoutes::register();');
    expect($web)->not->toContain("require __DIR__.'/auth.php';");
});

it('publica el panel y el perfil al terminar', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])->assertExitCode(0);

    foreach ([
        'app/Http/Controllers/DashboardController.php',
        'app/Http/Controllers/Profile/ProfileController.php',
        'app/Http/Controllers/Profile/PasswordController.php',
        'app/Http/Requests/Profile/UpdateProfileRequest.php',
        'app/Http/Requests/Profile/UpdatePasswordRequest.php',
        'routes/dashboard.php',
        'resources/js/Layouts/AppLayout.vue',
        'resources/js/Pages/Dashboard.vue',
        'resources/js/Pages/Profile/Edit.vue',
        'resources/js/Pages/Profile/Partials/UpdateProfileForm.vue',
        'resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue',
        'resources/js/Pages/Profile/Partials/DeleteAccountForm.vue',
    ] as $file) {
        expect(is_file($basePath.'/'.$file))->toBeTrue("falta {$file}");
    }

    expect((string) file_get_contents($basePath.'/routes/web.php'))
        ->toContain("require __DIR__.'/dashboard.php';");
});

it('comparte auth.user sin exponer el secreto de dos factores', function (): void {
    $basePath = ApplicationFixture::create(appWithStack() + [
        'app/Http/Middleware/HandleInertiaRequests.php' => <<<'PHP'
            <?php

            namespace App\Http\Middleware;

            use Illuminate\Http\Request;
            use Inertia\Middleware;

            class HandleInertiaRequests extends Middleware
            {
                public function share(Request $request): array
                {
                    return [
                        ...parent::share($request),
                        //
                    ];
                }
            }
            PHP,
    ]);

    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])->assertExitCode(0);

    $middleware = (string) file_get_contents($basePath.'/app/Http/Middleware/HandleInertiaRequests.php');

    expect($middleware)
        ->toContain("'auth' => [")
        ->toContain("'user' => \$request->user()?->only('id', 'name', 'email', 'email_verified_at'),")
        ->toContain("'flash' => [");

    // Compartir el modelo entero filtraría two_factor_secret al navegador.
    expect($middleware)->not->toContain("'user' => \$request->user(),");
});

it('--no-dashboard omite el panel y el perfil', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit', '--no-dashboard' => true])->assertExitCode(0);

    expect(is_file($basePath.'/resources/js/Pages/Dashboard.vue'))->toBeFalse();
    expect((string) file_get_contents($basePath.'/routes/web.php'))->not->toContain('dashboard.php');
});

it('publicar el panel es idempotente', function (): void {
    $basePath = ApplicationFixture::create(appWithStack());
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])->assertExitCode(0);
    $first = ApplicationFixture::snapshot($basePath);

    $this->artisan('primevuekit:auth', ['--strategy' => 'kit'])->assertExitCode(0);

    expect(ApplicationFixture::snapshot($basePath))->toBe($first);
});
