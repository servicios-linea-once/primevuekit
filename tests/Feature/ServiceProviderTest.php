<?php

declare(strict_types=1);

use PrimeVueKit\PrimeVueKitServiceProvider;

it('registra el service provider del paquete', function (): void {
    expect(app()->getLoadedProviders())
        ->toHaveKey(PrimeVueKitServiceProvider::class);
});

it('carga la configuración por defecto', function (): void {
    expect(config('primevuekit.prefix'))->toBe('pvk');
});

it('permite sobrescribir el prefijo desde la aplicación', function (): void {
    config()->set('primevuekit.prefix', 'app');

    expect(config('primevuekit.prefix'))->toBe('app');
});
