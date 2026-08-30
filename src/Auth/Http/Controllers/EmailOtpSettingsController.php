<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PrimeVueKit\Auth\Contracts\SupportsEmailOtp;

class EmailOtpSettingsController
{
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof SupportsEmailOtp) {
            abort(404);
        }

        $user->enableEmailOtp();

        return back()->with('status', __('Código por correo activado.'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof SupportsEmailOtp) {
            abort(404);
        }

        $user->disableEmailOtp();

        return back()->with('status', __('Código por correo desactivado.'));
    }
}
