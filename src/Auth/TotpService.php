<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth;

use PragmaRX\Google2FA\Google2FA;

/**
 * Envoltura fina sobre Google2FA con la configuración del kit ya aplicada.
 *
 * Google2FA sólo genera el secreto, verifica el código y construye la URI
 * `otpauth://`; el dibujo del QR es cosa de {@see QrCodeSvg}.
 */
final class TotpService
{
    public function __construct(
        private readonly Google2FA $engine,
        private readonly string $issuer,
        private readonly int $secretLength = 32,
        private readonly int $digits = 6,
        private readonly int $window = 1,
    ) {
        $this->engine->setOneTimePasswordLength($this->digits);
    }

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey($this->secretLength);
    }

    /**
     * Devuelve el contador de 30 segundos del código aceptado, o null si no es válido.
     *
     * Siempre se pasa `$lastUsedCounter` (0 cuando no hay ninguno previo) para que
     * Google2FA devuelva el contador en lugar de un booleano: guardándolo se rechaza
     * la reutilización del mismo código dentro de la ventana de tolerancia.
     */
    public function verify(string $secret, string $code, int $lastUsedCounter = 0): ?int
    {
        $result = $this->engine->verifyKeyNewer($secret, $code, $lastUsedCounter, $this->window);

        return is_int($result) ? $result : null;
    }

    public function provisioningUri(string $secret, string $account): string
    {
        return $this->engine->getQRCodeUrl($this->issuer, $account, $secret);
    }

    /**
     * Código válido en este instante. Es lo que mostraría una app de autenticación;
     * se usa en las pruebas y en el flujo de confirmación del enrolamiento.
     */
    public function currentCode(string $secret): string
    {
        return $this->engine->getCurrentOtp($secret);
    }
}
