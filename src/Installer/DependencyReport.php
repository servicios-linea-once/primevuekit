<?php

declare(strict_types=1);

namespace PrimeVueKit\Installer;

use PrimeVueKit\Enums\DependencyState;

final readonly class DependencyReport
{
    public function __construct(
        public Dependency $dependency,
        public DependencyState $state,
        public ?string $detectedVersion,
    ) {}

    public function name(): string
    {
        return $this->dependency->name;
    }

    /**
     * Major detectada, para comprobar la coherencia entre paquetes que deben ir a la par.
     */
    public function detectedMajor(): ?string
    {
        if ($this->detectedVersion === null) {
            return null;
        }

        $version = Dependency::normalizeVersion($this->detectedVersion);

        if ($version === '') {
            return null;
        }

        return explode('.', $version)[0];
    }

    public function describe(): string
    {
        $label = $this->state->label();

        if ($this->detectedVersion === null) {
            return $label;
        }

        return sprintf('%s (%s)', $label, $this->detectedVersion);
    }
}
