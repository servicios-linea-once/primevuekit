<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Resuelve el modelo de usuario configurado en `auth.providers.users.model`.
 *
 * Los controladores del kit no pueden referenciar `App\Models\User` directamente: el
 * paquete no conoce el namespace de la aplicación y el modelo es configurable.
 */
final class UserModel
{
    /**
     * La comprobación en tiempo de ejecución es lo que justifica el tipo intersección.
     *
     * @return class-string<Model&Authenticatable>
     */
    public static function class(): string
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model)
            || ! class_exists($model)
            || ! is_subclass_of($model, Model::class)
            || ! is_subclass_of($model, Authenticatable::class)
        ) {
            throw new RuntimeException(
                'auth.providers.users.model debe apuntar a un modelo Eloquent autenticable.'
            );
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): Model&Authenticatable
    {
        $class = self::class();

        $user = new $class;
        $user->fill($attributes)->save();

        return $user;
    }
}
