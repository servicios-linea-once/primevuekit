<?php

declare(strict_types=1);

use PragmaRX\Google2FA\Google2FA;
use PrimeVueKit\Auth\QrCodeSvg;
use PrimeVueKit\Auth\TotpService;

function totp(int $window = 1, int $digits = 6): TotpService
{
    return new TotpService(new Google2FA, issuer: 'PrimeVueKit', secretLength: 32, digits: $digits, window: $window);
}

it('genera secretos base32 de la longitud configurada', function (): void {
    $secret = totp()->generateSecret();

    expect($secret)->toHaveLength(32)
        ->and($secret)->toMatch('/^[A-Z2-7]+$/');
});

it('acepta el código actual y devuelve su contador', function (): void {
    $service = totp();
    $secret = $service->generateSecret();

    $counter = $service->verify($secret, $service->currentCode($secret));

    expect($counter)->toBeInt()
        // El contador es time()/30; se admite ±1 para no romper si el test cruza el paso.
        ->and(abs((int) $counter - intdiv(time(), 30)))->toBeLessThanOrEqual(1);
});

it('rechaza un código incorrecto', function (): void {
    $service = totp();
    $secret = $service->generateSecret();

    expect($service->verify($secret, '000000'))->toBeNull();
});

it('rechaza reutilizar el mismo código dentro de la ventana', function (): void {
    $service = totp();
    $secret = $service->generateSecret();
    $code = $service->currentCode($secret);

    $counter = $service->verify($secret, $code);

    expect($counter)->toBeInt()
        ->and($service->verify($secret, $code, (int) $counter))->toBeNull();
});

it('construye una URI otpauth con el emisor y la cuenta', function (): void {
    $service = totp();
    $secret = $service->generateSecret();

    $uri = $service->provisioningUri($secret, 'ana@example.test');

    expect($uri)->toStartWith('otpauth://totp/PrimeVueKit:ana%40example.test')
        ->toContain('secret='.$secret)
        ->toContain('issuer=PrimeVueKit')
        ->toContain('digits=6');
});

it('renderiza el QR como SVG inline listo para incrustar', function (): void {
    $svg = (new QrCodeSvg(size: 200, margin: 1))->render('otpauth://totp/PrimeVueKit:ana?secret=ABCDEFGHIJKLMNOP');

    expect($svg)->toStartWith('<svg')
        ->and($svg)->not->toContain('<?xml')
        ->and($svg)->toContain('width="200"');
});
