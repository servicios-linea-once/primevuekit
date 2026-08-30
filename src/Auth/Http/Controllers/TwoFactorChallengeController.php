<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PrimeVueKit\Auth\ChallengeSession;
use PrimeVueKit\Auth\Contracts\SupportsEmailOtp;
use PrimeVueKit\Auth\Contracts\SupportsTotp;

/**
 * Reto TOTP. El usuario todavía no está autenticado: sólo hay un reto en sesión.
 */
class TwoFactorChallengeController
{
    public function __construct(private readonly ChallengeSession $challenge) {}

    public function create(Request $request): Response
    {
        $user = $this->challenge->user($request);

        return Inertia::render('Auth/TwoFactorChallenge', [
            // Sólo se ofrece la vía del correo si el usuario la tiene activada.
            'canUseEmailOtp' => $user instanceof SupportsEmailOtp
                && $user->hasEnabledEmailOtp()
                && Route::has('otp.challenge'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $user = $this->challenge->user($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $code = $request->string('code')->toString();
        $recovery = $request->string('recovery_code')->toString();

        $verified = match (true) {
            ! $user instanceof SupportsTotp => false,
            $code !== '' => $user->verifyTotpCode($code),
            $recovery !== '' => $user->useTotpRecoveryCode($recovery),
            default => false,
        };

        if (! $verified) {
            $field = $recovery !== '' ? 'recovery_code' : 'code';

            throw ValidationException::withMessages([
                $field => $field === 'code'
                    ? __('El código no es válido o ya se ha usado.')
                    : __('El código de recuperación no es válido.'),
            ]);
        }

        $this->challenge->complete($request, $user);

        return redirect()->intended(AuthenticatedSessionController::home());
    }
}
