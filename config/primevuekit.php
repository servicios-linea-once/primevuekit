<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Prefijo de componentes
    |--------------------------------------------------------------------------
    |
    | Prefijo aplicado a los componentes que el paquete registre o publique.
    | Mantenerlo permite convivir con componentes propios de la aplicación
    | sin colisiones de nombres.
    |
    */

    'prefix' => env('PRIMEVUEKIT_PREFIX', 'pvk'),

    /*
    |--------------------------------------------------------------------------
    | Autenticación
    |--------------------------------------------------------------------------
    |
    | Parámetros del segundo factor. Los valores por defecto son deliberadamente
    | conservadores: el TTL del código por correo es mucho más corto que los 60
    | minutos que Laravel usa para el restablecimiento de contraseña, porque un
    | código de seis dígitos tiene un espacio de búsqueda muy pequeño y la
    | protección real es la combinación de caducidad y límite de intentos.
    |
    */

    'auth' => [

        'home' => env('PRIMEVUEKIT_HOME', '/'),

        'otp' => [
            'length' => 6,
            'ttl' => 300,
            'max_attempts' => 5,
            'resend_after' => 60,
            'max_per_hour' => 5,
        ],

        'totp' => [
            'issuer' => env('PRIMEVUEKIT_TOTP_ISSUER'),
            'digits' => 6,
            'window' => 1,
            'secret_length' => 32,
            'recovery_codes' => 8,
            'qr_size' => 256,
            'qr_margin' => 1,
        ],

    ],

];
