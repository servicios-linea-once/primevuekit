<?php

declare(strict_types=1);

use PrimeVueKit\Enums\ScaffoldOutcome;
use PrimeVueKit\Installer\ApplicationScaffolder;
use PrimeVueKit\Tests\Fixtures\ApplicationFixture;

it('registra el plugin de Vue en vite.config.js', function (): void {
    $basePath = ApplicationFixture::create();

    expect((new ApplicationScaffolder($basePath))->viteConfig())->toBe(ScaffoldOutcome::Applied);

    expect(file_get_contents($basePath.'/vite.config.js'))
        ->toContain("import vue from '@vitejs/plugin-vue';")
        ->toContain('        vue(),');
});

it('no vuelve a tocar vite.config.js si el plugin ya está registrado', function (): void {
    $basePath = ApplicationFixture::create([
        'vite.config.js' => "import vue from '@vitejs/plugin-vue';\n",
    ]);

    expect((new ApplicationScaffolder($basePath))->viteConfig())->toBe(ScaffoldOutcome::Skipped);
});

it('escribe el bootstrap de Inertia conservando el import de echo', function (): void {
    $basePath = ApplicationFixture::create();

    expect((new ApplicationScaffolder($basePath))->appJavaScript())->toBe(ScaffoldOutcome::Applied);

    expect(file_get_contents($basePath.'/resources/js/app.js'))
        ->toContain("import './echo';")
        ->toContain('createInertiaApp(')
        ->toContain("import { ZiggyVue } from 'ziggy-js';")
        ->toContain("import Aura from '@primeuix/themes/aura';")
        ->toContain("cssLayer: { name: 'primevue', order: 'theme, base, primevue' }");
});

it('omite el import de echo cuando la aplicación no usa Echo', function (): void {
    $basePath = ApplicationFixture::create(['resources/js/app.js' => "//\n"]);

    unlink($basePath.'/resources/js/echo.js');

    (new ApplicationScaffolder($basePath))->appJavaScript();

    expect(file_get_contents($basePath.'/resources/js/app.js'))
        ->not->toContain("import './echo';")
        ->toContain('createInertiaApp(');
});

it('añade tailwindcss-primeui después del import de tailwind', function (): void {
    $basePath = ApplicationFixture::create();

    expect((new ApplicationScaffolder($basePath))->appCss())->toBe(ScaffoldOutcome::Applied);

    expect(file_get_contents($basePath.'/resources/css/app.css'))
        ->toContain("@import 'tailwindcss';\n@import 'tailwindcss-primeui';");
});

it('crea la vista raíz con las directivas de Inertia y Ziggy', function (): void {
    $basePath = ApplicationFixture::create();

    expect((new ApplicationScaffolder($basePath))->rootView())->toBe(ScaffoldOutcome::Applied);

    $view = file_get_contents($basePath.'/resources/views/app.blade.php');

    expect($view)
        ->toContain('@routes')
        ->toContain('<x-inertia::head />')
        ->toContain('<x-inertia::app />');

    // Ziggy exige que @routes vaya antes de la carga del bundle.
    expect(strpos((string) $view, '@routes'))->toBeLessThan(strpos((string) $view, '@vite'));
});

it('registra HandleInertiaRequests en bootstrap/app.php', function (): void {
    $basePath = ApplicationFixture::create();

    expect((new ApplicationScaffolder($basePath))->inertiaMiddleware())->toBe(ScaffoldOutcome::Applied);

    expect(file_get_contents($basePath.'/bootstrap/app.php'))
        ->toContain('use App\Http\Middleware\HandleInertiaRequests;')
        ->toContain('$middleware->web(append: [')
        ->toContain('HandleInertiaRequests::class,');
});

it('pide ajuste manual cuando bootstrap/app.php no encaja con el patrón', function (): void {
    $basePath = ApplicationFixture::create([
        'bootstrap/app.php' => "<?php\n\nreturn 'algo completamente distinto';\n",
    ]);

    expect((new ApplicationScaffolder($basePath))->inertiaMiddleware())->toBe(ScaffoldOutcome::Manual);
});

it('añade la ruta de demostración una sola vez', function (): void {
    $basePath = ApplicationFixture::create();
    $scaffolder = new ApplicationScaffolder($basePath);

    expect($scaffolder->demoRoute())->toBe(ScaffoldOutcome::Applied);

    $routes = (string) file_get_contents($basePath.'/routes/web.php');

    expect($routes)
        ->toContain('use Inertia\Inertia;')
        ->toContain("->name('primevuekit.demo');");

    expect(substr_count($routes, 'primevuekit.demo'))->toBe(1);
    expect($scaffolder->demoRoute())->toBe(ScaffoldOutcome::Skipped);
});

it('crea la página Inertia de demostración', function (): void {
    $basePath = ApplicationFixture::create();

    expect((new ApplicationScaffolder($basePath))->demoPage())->toBe(ScaffoldOutcome::Applied);

    expect(file_get_contents($basePath.'/resources/js/Pages/PrimeVueKit/Welcome.vue'))
        ->toContain("import Button from 'primevue/button';")
        ->toContain("route('primevuekit.demo')");
});

it('es idempotente: una segunda ejecución no cambia ningún archivo', function (): void {
    $basePath = ApplicationFixture::create();
    $scaffolder = new ApplicationScaffolder($basePath);

    $scaffolder->run();
    $first = ApplicationFixture::snapshot($basePath);

    $outcomes = $scaffolder->run();

    expect(ApplicationFixture::snapshot($basePath))->toBe($first);
    expect($outcomes)->not->toContain(ScaffoldOutcome::Manual);
});
