<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use PrimeVueKit\Auth\Contracts\SupportsEmailOtp;
use PrimeVueKit\Auth\Contracts\SupportsTotp;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'security' => [
                'emailVerified' => ! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail(),
                'totpEnabled' => $user instanceof SupportsTotp && $user->hasEnabledTotp(),
                'emailOtpEnabled' => $user instanceof SupportsEmailOtp && $user->hasEnabledEmailOtp(),
                // Con la estrategia fortify el segundo factor se gestiona en sus propias rutas.
                'canManage' => Route::has('two-factor.show'),
            ],
        ]);
    }
}
