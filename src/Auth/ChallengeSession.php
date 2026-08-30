<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Estado del reto de segundo factor mientras el usuario todavía NO está autenticado.
 *
 * Se guarda en sesión el id del usuario y el flag «recuérdame», nunca el usuario
 * autenticado: `Auth::login()` sólo se llama cuando el reto se supera.
 */
final class ChallengeSession
{
    private const KEY = 'primevuekit.challenge';

    /**
     * Minutos que el usuario tiene para completar el reto antes de volver al login.
     */
    private const TTL_MINUTES = 10;

    public function start(Request $request, Authenticatable $user, bool $remember): void
    {
        $request->session()->put(self::KEY, [
            'id' => $user->getAuthIdentifier(),
            'remember' => $remember,
            'at' => time(),
        ]);
    }

    public function pending(Request $request): bool
    {
        return $this->user($request) !== null;
    }

    public function user(Request $request): ?Authenticatable
    {
        $state = $request->session()->get(self::KEY);

        if (! is_array($state) || ! isset($state['id'], $state['at'])) {
            return null;
        }

        if (time() - (int) $state['at'] > self::TTL_MINUTES * 60) {
            $this->clear($request);

            return null;
        }

        return Auth::getProvider()->retrieveById($state['id']);
    }

    public function remember(Request $request): bool
    {
        $state = $request->session()->get(self::KEY);

        return is_array($state) && ($state['remember'] ?? false) === true;
    }

    /**
     * Completa el login y descarta el estado del reto.
     */
    public function complete(Request $request, Authenticatable $user): void
    {
        $remember = $this->remember($request);

        $this->clear($request);

        Auth::login($user, $remember);

        $request->session()->regenerate();
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }
}
