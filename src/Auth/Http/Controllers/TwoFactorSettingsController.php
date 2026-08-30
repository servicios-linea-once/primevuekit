<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PrimeVueKit\Auth\Contracts\SupportsEmailOtp;
use PrimeVueKit\Auth\Contracts\SupportsTotp;

/**
 * Enrolamiento del segundo factor. Es el mínimo imprescindible para que TOTP y OTP,
 * que son obligatorios, se puedan activar y desactivar.
 *
 * Con la estrategia `fortify` el TOTP lo gestionan las rutas de Fortify, así que aquí
 * sólo queda la parte del código por correo.
 */
class TwoFactorSettingsController
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $totp = $user instanceof SupportsTotp ? $user : null;

        $totpEnabled = false;
        $pending = false;
        $qrCode = null;
        $recoveryCodes = [];

        if ($totp !== null) {
            $totpEnabled = $totp->hasEnabledTotp();
            $pending = $totp->hasPendingTotp();

            // El QR y los códigos sólo se exponen mientras el factor está sin confirmar.
            if ($pending) {
                $qrCode = $totp->totpQrCodeSvg();
                $recoveryCodes = $totp->totpRecoveryCodes();
            }
        }

        return Inertia::render('Auth/TwoFactor', [
            'totpEnabled' => $totpEnabled,
            'totpPending' => $pending,
            'emailOtpEnabled' => $user instanceof SupportsEmailOtp && $user->hasEnabledEmailOtp(),
            'qrCode' => $qrCode,
            'recoveryCodes' => $recoveryCodes,
            'status' => session('status'),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $this->totp($request)->startTotpEnrolment();

        return back()->with('status', __('Escanea el código y confírmalo para activarlo.'));
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        if (! $this->totp($request)->confirmTotp($request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => __('El código no es válido.')]);
        }

        return back()->with('status', __('Segundo factor de aplicación activado.'));
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $this->totp($request)->regenerateTotpRecoveryCodes();

        return back()->with('status', __('Códigos de recuperación regenerados.'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->totp($request)->disableTotp();

        return back()->with('status', __('Segundo factor de aplicación desactivado.'));
    }

    private function totp(Request $request): SupportsTotp
    {
        $user = $request->user();

        if (! $user instanceof SupportsTotp) {
            abort(404);
        }

        return $user;
    }
}
