<?php

declare(strict_types=1);

namespace PrimeVueKit\Installer;

use FilesystemIterator;
use PrimeVueKit\Enums\ScaffoldOutcome;
use PrimeVueKit\Installer\Concerns\EditsFiles;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Copia el panel y las páginas de perfil a la aplicación.
 *
 * A diferencia de la autenticación, esto se publica siempre: un panel y un perfil son de
 * cada proyecto y se editan desde el primer día, así que no tiene sentido servirlos desde
 * el paquete. Los stubs ya vienen con el namespace `App\`, no hay nada que reescribir.
 */
final class DashboardPublisher
{
    use EditsFiles;

    public function __construct(private readonly string $basePath) {}

    /**
     * @return array<string, ScaffoldOutcome>
     */
    public function publish(bool $force = false): array
    {
        return [
            'app/Http/Controllers y resources/js' => $this->copyTree($force),
            'routes/web.php' => $this->routes(),
            'app/Http/Middleware/HandleInertiaRequests.php' => $this->sharedProps(),
        ];
    }

    protected function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Copia `stubs/dashboard/**` sobre la raíz de la aplicación conservando la estructura.
     */
    private function copyTree(bool $force): ScaffoldOutcome
    {
        $root = dirname(__DIR__, 2).'/stubs/dashboard';

        if (! is_dir($root)) {
            return ScaffoldOutcome::Manual;
        }

        $outcome = ScaffoldOutcome::Skipped;
        $length = strlen($root) + 1;

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        ) as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), $length));

            if (is_file($this->path($relative)) && ! $force) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if ($contents === false) {
                return ScaffoldOutcome::Manual;
            }

            $this->put($relative, $contents, "\n");
            $outcome = ScaffoldOutcome::Applied;
        }

        return $outcome;
    }

    private function routes(): ScaffoldOutcome
    {
        $original = $this->read('routes/web.php');

        if ($original === null) {
            return ScaffoldOutcome::Manual;
        }

        $line = "require __DIR__.'/dashboard.php';";

        if (str_contains($original, $line)) {
            return ScaffoldOutcome::Skipped;
        }

        $content = rtrim($this->normalize($original), "\n")."\n\n".$line."\n";

        return $this->put('routes/web.php', $content, $this->eolOf($original));
    }

    /**
     * El layout necesita `auth.user` y `flash.status` en las props compartidas de Inertia.
     *
     * Se comparten sólo cuatro atributos, no el modelo entero: `two_factor_secret` y
     * `two_factor_recovery_codes` no están en `$hidden` y acabarían en el navegador.
     */
    private function sharedProps(): ScaffoldOutcome
    {
        $relative = 'app/Http/Middleware/HandleInertiaRequests.php';
        $original = $this->read($relative);

        if ($original === null) {
            return ScaffoldOutcome::Manual;
        }

        if (str_contains($original, "'auth' =>")) {
            return ScaffoldOutcome::Skipped;
        }

        $lines = $this->lines($original);
        $share = $this->firstMatching($lines, '/public function share\(/');

        if ($share === null) {
            return ScaffoldOutcome::Manual;
        }

        $placeholder = null;
        $limit = min(count($lines), $share + 8);

        for ($index = $share + 1; $index < $limit; $index++) {
            if (trim($lines[$index]) === '//') {
                $placeholder = $index;

                break;
            }
        }

        if ($placeholder === null) {
            return ScaffoldOutcome::Manual;
        }

        array_splice($lines, $placeholder, 1, $this->lines($this->sharedPropsBlock()));

        return $this->putLines($relative, $lines, $this->eolOf($original));
    }

    /**
     * Se construye línea a línea con la indentación explícita: los heredoc con sangrado
     * son fáciles de descuadrar y aquí el resultado se inserta dentro de un array.
     */
    private function sharedPropsBlock(): string
    {
        $indent = str_repeat(' ', 12);

        $lines = [
            '// Sólo estos atributos: el modelo completo expondría two_factor_secret.',
            "'auth' => [",
            "    'user' => \$request->user()?->only('id', 'name', 'email', 'email_verified_at'),",
            '],',
            "'flash' => [",
            "    'status' => fn () => \$request->session()->get('status'),",
            '],',
        ];

        return implode("\n", array_map(static fn (string $line): string => $indent.$line, $lines));
    }
}
