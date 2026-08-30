<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reconfirmación de contraseña: protege el enrolamiento del segundo factor.
 */
class ConfirmablePasswordController
{
    public function show(): Response
    {
        return Inertia::render('Auth/ConfirmPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        // Se compara contra el hash del propio usuario, sin depender de cuál sea el
        // campo de identificación del guard.
        $matches = Hash::check(
            $request->string('password')->toString(),
            (string) $request->user()?->getAuthPassword(),
        );

        if (! $matches) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(AuthenticatedSessionController::home());
    }
}
