<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use PrimeVueKit\Auth\ChallengeSession;
use PrimeVueKit\Auth\Http\Requests\LoginRequest;

class AuthenticatedSessionController
{
    public function __construct(private readonly ChallengeSession $challenge) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $pending = $request->authenticate();

        if ($pending === null) {
            $request->session()->regenerate();

            return redirect()->intended(self::home());
        }

        $this->challenge->start($request, $pending, $request->boolean('remember'));

        // Con TOTP activo se pide el código de la app; si no, se envía uno por correo.
        return redirect()->route(self::prefersTotp($pending) ? 'two-factor.challenge' : 'otp.challenge');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(self::home());
    }

    public static function home(): string
    {
        $home = config('primevuekit.auth.home', '/');

        return is_string($home) ? $home : '/';
    }

    /**
     * Reconoce el trait del kit y el de Fortify.
     */
    public static function prefersTotp(Authenticatable $user): bool
    {
        foreach (['hasEnabledTotp', 'hasEnabledTwoFactorAuthentication'] as $method) {
            if (method_exists($user, $method) && $user->{$method}() === true) {
                return true;
            }
        }

        return false;
    }
}
