<?php

declare(strict_types=1);

namespace PrimeVueKit\Installer;

/**
 * Una dependencia del stack, con la restricción exacta que el comando debe instalar.
 */
final readonly class Dependency
{
    public const ECOSYSTEM_COMPOSER = 'composer';

    public const ECOSYSTEM_NPM = 'npm';

    /**
     * @param  string  $series  Serie de versiones esperada ("4", "2", "0.6"). Se compara
     *                          contra la versión detectada para avisar de majors mezcladas.
     */
    private function __construct(
        public string $ecosystem,
        public string $name,
        public string $constraint,
        public string $series,
        public bool $dev,
    ) {}

    public static function composer(string $name, string $constraint, string $series): self
    {
        return new self(self::ECOSYSTEM_COMPOSER, $name, $constraint, $series, false);
    }

    public static function npm(string $name, string $constraint, string $series, bool $dev = false): self
    {
        return new self(self::ECOSYSTEM_NPM, $name, $constraint, $series, $dev);
    }

    public function isComposer(): bool
    {
        return $this->ecosystem === self::ECOSYSTEM_COMPOSER;
    }

    public function isNpm(): bool
    {
        return $this->ecosystem === self::ECOSYSTEM_NPM;
    }

    /**
     * Argumento tal y como lo espera el gestor de paquetes correspondiente.
     */
    public function installArgument(): string
    {
        return $this->isComposer()
            ? $this->name.':'.$this->constraint
            : $this->name.'@'.$this->constraint;
    }

    public function matchesSeries(string $version): bool
    {
        $version = self::normalizeVersion($version);

        if ($version === '') {
            return true;
        }

        return $version === $this->series || str_starts_with($version, $this->series.'.');
    }

    public static function normalizeVersion(string $version): string
    {
        $version = ltrim(trim($version), 'vV=');

        return preg_match('/^\d+(?:\.\d+)*/', $version, $matches) === 1 ? $matches[0] : '';
    }
}
