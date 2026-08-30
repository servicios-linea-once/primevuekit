<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use PrimeVueKit\Tests\Fixtures\ApplicationFixture;

/**
 * Aplana el comando registrado por el fake, que puede ser array o string.
 */
function commandLine(object $process): string
{
    /** @var array<int, string>|string $command */
    $command = $process->command;

    return is_array($command) ? implode(' ', $command) : $command;
}

beforeEach(function (): void {
    Process::fake();
    Process::preventStrayProcesses();
});

it('registra el comando en Artisan', function (): void {
    expect(Artisan::all())->toHaveKey('primevuekit:install');
});

it('rechaza una línea de PrimeVue no soportada', function (): void {
    $this->artisan('primevuekit:install', ['--primevue' => '9'])->assertExitCode(1);

    Process::assertNothingRan();
});

it('sale con 1 y sin ejecutar nada cuando falta todo el stack', function (): void {
    $this->app->setBasePath(ApplicationFixture::create());

    $this->artisan('primevuekit:install', ['--check' => true])->assertExitCode(1);

    Process::assertNothingRan();
});

it('sale con 0 cuando el stack ya está declarado', function (): void {
    $this->app->setBasePath(ApplicationFixture::create(ApplicationFixture::withStackDeclared()));

    $this->artisan('primevuekit:install', ['--check' => true])->assertExitCode(0);

    Process::assertNothingRan();
});

it('instala los paquetes que faltan de Composer y de Node', function (): void {
    $this->app->setBasePath(ApplicationFixture::create());

    $this->artisan('primevuekit:install', ['--no-scaffold' => true])->assertExitCode(0);

    Process::assertRan(fn (object $process): bool => str_contains($line = commandLine($process), 'composer require')
        && str_contains($line, 'inertiajs/inertia-laravel:^3.3')
        && str_contains($line, 'tightenco/ziggy:^2.6'));

    Process::assertRan(fn (object $process): bool => str_contains($line = commandLine($process), 'npm install')
        && str_contains($line, 'vue@^3.5.42')
        && str_contains($line, '@inertiajs/vue3@^3.7.0')
        && str_contains($line, 'ziggy-js@^2.6.4')
        && str_contains($line, 'primevue@^4.5.5')
        && str_contains($line, '@primeuix/themes@^2.0.3')
        && str_contains($line, 'primeicons@^7.0.0')
        && str_contains($line, '--ignore-scripts'));

    Process::assertRan(fn (object $process): bool => str_contains($line = commandLine($process), '--save-dev')
        && str_contains($line, '@vitejs/plugin-vue@^6.0.8')
        && str_contains($line, 'tailwindcss-primeui@^0.6.1'));
});

it('no reinstala lo que ya está declarado', function (): void {
    $this->app->setBasePath(ApplicationFixture::create(ApplicationFixture::withStackDeclared()));

    $this->artisan('primevuekit:install', ['--no-scaffold' => true])->assertExitCode(0);

    Process::assertDidntRun(fn (object $process): bool => str_contains(commandLine($process), 'composer require'));
    Process::assertDidntRun(fn (object $process): bool => str_contains(commandLine($process), 'npm install'));
});

it('fija las versiones de la línea 5.x y avisa de la licencia PrimeUI', function (): void {
    $this->app->setBasePath(ApplicationFixture::create());

    $this->artisan('primevuekit:install', ['--check' => true, '--primevue' => '5'])
        ->expectsOutputToContain('PrimeUI')
        ->assertExitCode(1);
});

it('aborta la línea 5.x si no se confirma la licencia', function (): void {
    $this->app->setBasePath(ApplicationFixture::create());

    $this->artisan('primevuekit:install', ['--primevue' => '5'])
        ->expectsConfirmation('¿Continuar con la línea 5.x bajo licencia PrimeUI?', 'no')
        ->assertExitCode(1);

    Process::assertNothingRan();
});

it('detecta líneas de PrimeVue mezcladas', function (): void {
    $this->app->setBasePath(ApplicationFixture::create([
        'package.json' => <<<'JSON'
            {
                "dependencies": {
                    "primevue": "^4.5.5",
                    "@primeuix/themes": "^3.0.0",
                    "primeicons": "^7.0.0"
                }
            }
            JSON,
    ]));

    $this->artisan('primevuekit:install', ['--check' => true])
        ->expectsOutputToContain('mezcladas')
        ->assertExitCode(1);

    Process::assertNothingRan();
});

it('delega en el servicio vite cuando no hay binario de npm', function (): void {
    // Un closure reemplaza por completo los handlers del fake global del beforeEach.
    Process::fake(fn (object $process): object => str_contains(commandLine($process), '--version')
        ? Process::result(errorOutput: 'not found', exitCode: 1)
        : Process::result());

    $this->app->setBasePath(ApplicationFixture::create());

    $this->artisan('primevuekit:install', ['--no-scaffold' => true])
        ->expectsOutputToContain('docker compose run --rm vite npm install')
        ->assertExitCode(0);

    Process::assertDidntRun(fn (object $process): bool => str_contains(commandLine($process), 'npm install'));
});

it('hace el wiring de la aplicación y no referencia el middleware si no existe', function (): void {
    $basePath = ApplicationFixture::create();
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:install')->assertExitCode(0);

    expect(file_get_contents($basePath.'/vite.config.js'))->toContain('@vitejs/plugin-vue');
    expect(file_get_contents($basePath.'/resources/js/app.js'))->toContain('createInertiaApp(');
    expect(file_get_contents($basePath.'/resources/views/app.blade.php'))->toContain('<x-inertia::app />');
    expect(file_get_contents($basePath.'/routes/web.php'))->toContain('primevuekit.demo');

    // El generador de Inertia está simulado, así que la clase no existe y bootstrap/app.php
    // no debe apuntar a ella: si lo hiciera, toda petición fallaría.
    expect(file_get_contents($basePath.'/bootstrap/app.php'))->not->toContain('HandleInertiaRequests');
});

it('omite la demo con --no-demo', function (): void {
    $basePath = ApplicationFixture::create();
    $this->app->setBasePath($basePath);

    $this->artisan('primevuekit:install', ['--no-demo' => true])->assertExitCode(0);

    expect(file_get_contents($basePath.'/routes/web.php'))->not->toContain('primevuekit.demo');
    expect(is_file($basePath.'/resources/js/Pages/PrimeVueKit/Welcome.vue'))->toBeFalse();
});
