<?php

declare(strict_types=1);

namespace PrimeVueKit\Installer;

use PrimeVueKit\Enums\ScaffoldOutcome;
use PrimeVueKit\Installer\Concerns\EditsFiles;

/**
 * Aplica el wiring mínimo de Inertia + Vue + PrimeVue + Ziggy sobre la aplicación.
 *
 * Todas las operaciones son idempotentes y ninguna lanza excepción cuando el archivo no
 * encaja con el patrón esperado: devuelven `ScaffoldOutcome::Manual` para que el comando
 * imprima la instrucción manual, igual que hace el instalador de broadcasting del framework.
 */
final class ApplicationScaffolder
{
    use EditsFiles;

    public function __construct(private readonly string $basePath) {}

    /**
     * @return array<string, ScaffoldOutcome>
     */
    public function run(bool $force = false, bool $withDemo = true, bool $withMiddleware = true): array
    {
        $results = [
            'vite.config.js' => $this->viteConfig(),
            'resources/js/app.js' => $this->appJavaScript($force),
            'resources/css/app.css' => $this->appCss(),
            'resources/views/app.blade.php' => $this->rootView($force),
        ];

        // Sólo se referencia el middleware si la clase existe: si no, bootstrap/app.php
        // apuntaría a una clase inexistente y toda petición fallaría.
        if ($withMiddleware) {
            $results['bootstrap/app.php'] = $this->inertiaMiddleware();
        }

        if ($withDemo) {
            $results['routes/web.php'] = $this->demoRoute();
            $results['resources/js/Pages/PrimeVueKit/Welcome.vue'] = $this->demoPage($force);
        }

        return $results;
    }

    public function viteConfig(): ScaffoldOutcome
    {
        $original = $this->read('vite.config.js');

        if ($original === null) {
            return ScaffoldOutcome::Manual;
        }

        if (str_contains($original, '@vitejs/plugin-vue')) {
            return ScaffoldOutcome::Skipped;
        }

        $lines = $this->lines($original);
        $lastImport = $this->lastMatching($lines, '/^import\s.+;\s*$/');

        if ($lastImport === null) {
            return ScaffoldOutcome::Manual;
        }

        $extraIndent = '';
        $anchor = $this->firstMatching($lines, '/^\s*tailwindcss\(\),\s*$/');

        if ($anchor === null) {
            $anchor = $this->firstMatching($lines, '/^\s*plugins:\s*\[\s*$/');
            $extraIndent = '    ';
        }

        if ($anchor === null) {
            return ScaffoldOutcome::Manual;
        }

        array_splice($lines, $anchor + 1, 0, [$this->indentOf($lines[$anchor]).$extraIndent.'vue(),']);
        array_splice($lines, $lastImport + 1, 0, ["import vue from '@vitejs/plugin-vue';"]);

        return $this->putLines('vite.config.js', $lines, $this->eolOf($original));
    }

    public function appJavaScript(bool $force = false): ScaffoldOutcome
    {
        $original = $this->read('resources/js/app.js');

        if ($original !== null && str_contains($original, 'createInertiaApp') && ! $force) {
            return ScaffoldOutcome::Skipped;
        }

        $stub = $this->stub('app.js.stub');

        // Sólo conserva el bootstrap de Echo si la aplicación realmente lo tiene.
        $keepEcho = is_file($this->path('resources/js/echo.js'))
            || ($original !== null && str_contains($original, './echo'));

        if (! $keepEcho) {
            $stub = preg_replace("#^import '\./echo';\n\n#", '', $stub) ?? $stub;
        }

        return $this->put('resources/js/app.js', $stub, $original === null ? "\n" : $this->eolOf($original));
    }

    public function appCss(): ScaffoldOutcome
    {
        $original = $this->read('resources/css/app.css');

        if ($original === null) {
            return ScaffoldOutcome::Manual;
        }

        if (str_contains($original, 'tailwindcss-primeui')) {
            return ScaffoldOutcome::Skipped;
        }

        $content = $this->normalize($original);

        foreach (["@import 'tailwindcss';", '@import "tailwindcss";'] as $needle) {
            if (! str_contains($content, $needle)) {
                continue;
            }

            return $this->put(
                'resources/css/app.css',
                str_replace($needle, $needle."\n@import 'tailwindcss-primeui';", $content),
                $this->eolOf($original),
            );
        }

        return ScaffoldOutcome::Manual;
    }

    public function rootView(bool $force = false): ScaffoldOutcome
    {
        if (is_file($this->path('resources/views/app.blade.php')) && ! $force) {
            return ScaffoldOutcome::Skipped;
        }

        return $this->put('resources/views/app.blade.php', $this->stub('app.blade.php.stub'), "\n");
    }

    public function inertiaMiddleware(): ScaffoldOutcome
    {
        $original = $this->read('bootstrap/app.php');

        if ($original === null) {
            return ScaffoldOutcome::Manual;
        }

        if (str_contains($original, 'HandleInertiaRequests')) {
            return ScaffoldOutcome::Skipped;
        }

        $lines = $this->lines($original);
        $firstUse = $this->firstMatching($lines, '/^use\s.+;\s*$/');
        $closure = $this->firstMatching($lines, '/->withMiddleware\(\s*function\s*\(/');

        if ($firstUse === null || $closure === null) {
            return ScaffoldOutcome::Manual;
        }

        $block = [
            '        $middleware->web(append: [',
            '            HandleInertiaRequests::class,',
            '        ]);',
        ];

        $placeholder = $this->placeholderAfter($lines, $closure);

        if ($placeholder === null) {
            array_splice($lines, $closure + 1, 0, $block);
        } else {
            array_splice($lines, $placeholder, 1, $block);
        }

        // Se inserta el `use` al final para no desplazar los índices calculados arriba.
        array_splice($lines, $firstUse, 0, ['use App\Http\Middleware\HandleInertiaRequests;']);

        return $this->putLines('bootstrap/app.php', $lines, $this->eolOf($original));
    }

    public function demoRoute(): ScaffoldOutcome
    {
        $original = $this->read('routes/web.php');

        if ($original === null) {
            return ScaffoldOutcome::Manual;
        }

        if (str_contains($original, 'primevuekit.demo')) {
            return ScaffoldOutcome::Skipped;
        }

        $lines = $this->lines($original);

        if (! str_contains($original, 'use Inertia\Inertia;')) {
            $lastUse = $this->lastMatching($lines, '/^use\s.+;\s*$/');

            if ($lastUse === null) {
                return ScaffoldOutcome::Manual;
            }

            array_splice($lines, $lastUse + 1, 0, ['use Inertia\Inertia;']);
        }

        $content = rtrim(implode("\n", $lines), "\n")."\n\n".$this->stub('demo-route.stub');

        return $this->put('routes/web.php', $content, $this->eolOf($original));
    }

    public function demoPage(bool $force = false): ScaffoldOutcome
    {
        $relative = 'resources/js/Pages/PrimeVueKit/Welcome.vue';

        if (is_file($this->path($relative)) && ! $force) {
            return ScaffoldOutcome::Skipped;
        }

        return $this->put($relative, $this->stub('Welcome.vue.stub'), "\n");
    }

    /**
     * Índice de la línea `//` que Laravel deja como cuerpo vacío del closure.
     *
     * @param  list<string>  $lines
     */
    private function placeholderAfter(array $lines, int $from): ?int
    {
        $limit = min(count($lines), $from + 6);

        for ($index = $from + 1; $index < $limit; $index++) {
            if (trim($lines[$index]) === '//') {
                return $index;
            }

            if (str_contains($lines[$index], '})')) {
                break;
            }
        }

        return null;
    }

    protected function basePath(): string
    {
        return $this->basePath;
    }
}
