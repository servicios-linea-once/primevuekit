<?php

declare(strict_types=1);

namespace PrimeVueKit\Installer\Concerns;

use PrimeVueKit\Enums\ScaffoldOutcome;
use RuntimeException;

/**
 * Utilidades de reescritura de archivos compartidas por los scaffolders.
 *
 * Se trabaja siempre con `\n` internamente y se restituye el fin de línea original al
 * escribir, para no reescribir un archivo entero por un cambio de CRLF.
 */
trait EditsFiles
{
    abstract protected function basePath(): string;

    protected function read(string $relative): ?string
    {
        $path = $this->path($relative);

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    /**
     * @return list<string>
     */
    protected function lines(string $content): array
    {
        return explode("\n", $this->normalize($content));
    }

    protected function normalize(string $content): string
    {
        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    /**
     * @param  list<string>  $lines
     */
    protected function putLines(string $relative, array $lines, string $eol): ScaffoldOutcome
    {
        return $this->put($relative, implode("\n", $lines), $eol);
    }

    protected function put(string $relative, string $content, string $eol): ScaffoldOutcome
    {
        $path = $this->path($relative);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return ScaffoldOutcome::Manual;
        }

        $content = $this->normalize($content);

        if ($eol !== "\n") {
            $content = str_replace("\n", $eol, $content);
        }

        return file_put_contents($path, $content) === false
            ? ScaffoldOutcome::Manual
            : ScaffoldOutcome::Applied;
    }

    protected function stub(string $name): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3).'/stubs/'.$name);

        if ($contents === false) {
            throw new RuntimeException("No se pudo leer el stub [{$name}].");
        }

        return $this->normalize($contents);
    }

    protected function path(string $relative): string
    {
        return rtrim($this->basePath(), '/\\').DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    protected function eolOf(string $content): string
    {
        return str_contains($content, "\r\n") ? "\r\n" : "\n";
    }

    /**
     * @param  list<string>  $lines
     */
    protected function firstMatching(array $lines, string $pattern): ?int
    {
        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    protected function lastMatching(array $lines, string $pattern): ?int
    {
        $found = null;

        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                $found = $index;
            }
        }

        return $found;
    }

    protected function indentOf(string $line): string
    {
        return preg_match('/^\s*/', $line, $matches) === 1 ? $matches[0] : '';
    }
}
