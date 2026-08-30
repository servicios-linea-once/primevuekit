<?php

declare(strict_types=1);

namespace PrimeVueKit\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PrimeVueKit\PrimeVueKitServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PrimeVueKitServiceProvider::class,
        ];
    }
}
