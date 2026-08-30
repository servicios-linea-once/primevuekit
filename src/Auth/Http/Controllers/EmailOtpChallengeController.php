<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PrimeVueKit\Auth\ChallengeSession;
use PrimeVueKit\Auth\Contracts\SupportsEmailOtp;
use PrimeVueKit\Auth\EmailOtpService;

/**
 * Reto del código de un solo uso enviado por correo.
 */
class EmailOtpChallengeController
{
    public function __construct(
        private readonly ChallengeSession $challenge,
        private readonly EmailOtpService $codes,
    ) {}

    /**
     * Al entrar se emite un código si no hay ninguno usable pendiente.
     */
    public function create(Request $request): RedirectResponse|Response
    {
        $user = $this->challenge->user($request);

        if (! $user instanceof SupportsEmailOtp) {
            return redirect()->route('login');
        }

        if ($this->codes->pendingCode($user) === null) {
            $user->sendEmailOtp();
        }

        return Inertia::render('Auth/EmailOtpChallenge', [
            'email' => $this->maskEmail((string) $user->getAttribute('email')),
            'secondsUntilResend' => $this->codes->secondsUntilResend($user),
            'ttlMinutes' => (int) ceil($this->codes->ttl() / 60),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $this->challenge->user($request);

        if (! $user instanceof SupportsEmailOtp) {
            return redirect()->route('login');
        }

        if (! $user->verifyEmailOtp($request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => __('El código no es válido, ha caducado o se han agotado los intentos.'),
            ]);
        }

        $this->challenge->complete($request, $user);

        return redirect()->intended(AuthenticatedSessionController::home());
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->challenge->user($request);

        if (! $user instanceof SupportsEmailOtp) {
            return redirect()->route('login');
        }

        if (! $user->sendEmailOtp()) {
            throw ValidationException::withMessages([
                'code' => __('Espera :seconds segundos antes de pedir otro código.', [
                    'seconds' => $this->codes->secondsUntilResend($user),
                ]),
            ]);
        }

        return back()->with('status', __('Te hemos enviado un código nuevo.'));
    }

    /**
     * Enmascara la dirección para no revelarla entera en una pantalla previa al login.
     */
    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($name, 0, 2);

        return $visible.str_repeat('*', max(1, mb_strlen($name) - 2)).'@'.$domain;
    }
}
