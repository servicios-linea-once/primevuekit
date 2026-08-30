<?php

declare(strict_types=1);

namespace PrimeVueKit\Tests\Fixtures;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Crea en un directorio temporal el esqueleto mínimo de la aplicación que el instalador
 * tiene que modificar. Los contenidos replican los archivos reales del repositorio.
 */
final class ApplicationFixture
{
    /**
     * @var list<string>
     */
    private static array $created = [];

    /**
     * @param  array<string, string>  $overrides  ruta relativa => contenido
     */
    public static function create(array $overrides = []): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'primevuekit-'.bin2hex(random_bytes(8));

        self::$created[] = $path;

        foreach (array_merge(self::files(), $overrides) as $relative => $contents) {
            self::put($path, $relative, $contents);
        }

        return $path;
    }

    public static function cleanup(): void
    {
        foreach (self::$created as $path) {
            self::delete($path);
        }

        self::$created = [];
    }

    public static function put(string $basePath, string $relative, string $contents): void
    {
        $target = $basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear el directorio [{$directory}].");
        }

        file_put_contents($target, $contents);
    }

    /**
     * Instantánea de todos los archivos del fixture, para comprobar idempotencia.
     *
     * @return array<string, string>
     */
    public static function snapshot(string $basePath): array
    {
        $snapshot = [];
        $length = strlen($basePath) + 1;

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            $snapshot[str_replace('\\', '/', substr($file->getPathname(), $length))] = $contents === false ? '' : $contents;
        }

        ksort($snapshot);

        return $snapshot;
    }

    private static function delete(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        ) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }

    /**
     * @return array<string, string>
     */
    public static function files(): array
    {
        return [
            'composer.json' => <<<'JSON'
                {
                    "require": {
                        "laravel/framework": "^13.17"
                    }
                }
                JSON,
            'package.json' => <<<'JSON'
                {
                    "private": true,
                    "type": "module",
                    "devDependencies": {
                        "@tailwindcss/vite": "^4.0.0",
                        "laravel-vite-plugin": "^3.1",
                        "tailwindcss": "^4.0.0",
                        "vite": "^8.0.0"
                    }
                }
                JSON,
            'vite.config.js' => <<<'JS'
                import { defineConfig } from 'vite';
                import laravel from 'laravel-vite-plugin';
                import { bunny } from 'laravel-vite-plugin/fonts';
                import tailwindcss from '@tailwindcss/vite';

                export default defineConfig({
                    plugins: [
                        laravel({
                            input: ['resources/css/app.css', 'resources/js/app.js'],
                            refresh: true,
                        }),
                        tailwindcss(),
                    ],
                });
                JS,
            'resources/js/app.js' => "//\n\nimport './echo';\n",
            'resources/js/echo.js' => "import Echo from 'laravel-echo';\n",
            'resources/css/app.css' => <<<'CSS'
                @import 'tailwindcss';

                @source '../../storage/framework/views/*.php';
                CSS,
            'bootstrap/app.php' => <<<'PHP'
                <?php

                use Illuminate\Foundation\Application;
                use Illuminate\Foundation\Configuration\Exceptions;
                use Illuminate\Foundation\Configuration\Middleware;
                use Illuminate\Http\Request;

                return Application::configure(basePath: dirname(__DIR__))
                    ->withRouting(
                        web: __DIR__.'/../routes/web.php',
                        commands: __DIR__.'/../routes/console.php',
                        health: '/up',
                    )
                    ->withMiddleware(function (Middleware $middleware): void {
                        //
                    })
                    ->withExceptions(function (Exceptions $exceptions): void {
                        //
                    })->create();
                PHP,
            'routes/web.php' => <<<'PHP'
                <?php

                use Illuminate\Support\Facades\Route;

                Route::get('/', function () {
                    return view('welcome');
                });
                PHP,
        ];
    }

    /**
     * Manifiestos con todo el stack de la línea MIT ya declarado.
     *
     * @return array<string, string>
     */
    public static function withStackDeclared(): array
    {
        return [
            'composer.json' => <<<'JSON'
                {
                    "require": {
                        "laravel/framework": "^13.17",
                        "inertiajs/inertia-laravel": "^3.3",
                        "tightenco/ziggy": "^2.6"
                    }
                }
                JSON,
            'package.json' => <<<'JSON'
                {
                    "private": true,
                    "type": "module",
                    "dependencies": {
                        "@inertiajs/vue3": "^3.7.0",
                        "@primeuix/themes": "^2.0.3",
                        "primeicons": "^7.0.0",
                        "primevue": "^4.5.5",
                        "vue": "^3.5.42",
                        "ziggy-js": "^2.6.4"
                    },
                    "devDependencies": {
                        "@tailwindcss/vite": "^4.0.0",
                        "@vitejs/plugin-vue": "^6.0.8",
                        "laravel-vite-plugin": "^3.1",
                        "tailwindcss": "^4.0.0",
                        "tailwindcss-primeui": "^0.6.1",
                        "vite": "^8.0.0"
                    }
                }
                JSON,
        ];
    }
}
