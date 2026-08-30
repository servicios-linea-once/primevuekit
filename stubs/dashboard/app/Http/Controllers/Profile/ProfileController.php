<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'emailVerified' => ! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail(),
            'status' => session('status'),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->validated());

        // Cambiar el correo invalida la verificación: hay que volver a comprobarlo.
        if ($user->isDirty('email') && $user instanceof MustVerifyEmail) {
            $user->forceFill(['email_verified_at' => null]);
            $user->save();
            $user->sendEmailVerificationNotification();

            return back()->with('status', __('Perfil actualizado. Revisa tu correo para verificar la dirección nueva.'));
        }

        $user->save();

        return back()->with('status', __('Perfil actualizado.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();

        if (! Hash::check($request->string('password')->toString(), (string) $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        Auth::guard('web')->logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
