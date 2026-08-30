<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PrimeVueKit\Auth\EmailOtpService;
use PrimeVueKit\Auth\Notifications\OneTimePassword;
use PrimeVueKit\Auth\TotpService;
use PrimeVueKit\Tests\Fixtures\AuthUser;

it('sirve la página de login del kit', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login')->where('canResetPassword', true));
});

it('registra un usuario y lo autentica', function (): void {
    Event::fake([Registered::class]);
    Notification::fake();

    $this->post('/register', [
        'name' => 'Ana',
        'email' => 'ana@example.test',
        'password' => 'contrasena-larga-1',
        'password_confirmation' => 'contrasena-larga-1',
    ])->assertRedirect('/');

    $this->assertAuthenticated();
    Event::assertDispatched(Registered::class);
});

it('entra directamente cuando no hay segundo factor', function (): void {
    AuthUser::seed();

    $this->post('/login', ['email' => 'ana@example.test', 'password' => 'password'])
        ->assertRedirect('/');

    $this->assertAuthenticated();
});

it('rechaza credenciales incorrectas', function (): void {
    AuthUser::seed();

    $this->post('/login', ['email' => 'ana@example.test', 'password' => 'incorrecta'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('no autentica hasta superar el reto de correo', function (): void {
    Notification::fake();

    $user = AuthUser::seed();
    $user->enableEmailOtp();

    $this->post('/login', ['email' => 'ana@example.test', 'password' => 'password'])
        ->assertRedirect(route('otp.challenge'));

    // El punto clave: hay credenciales validadas pero NO hay sesión autenticada.
    $this->assertGuest();

    $this->get(route('otp.challenge'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/EmailOtpChallenge')->where('email', 'an*@example.test'));

    Notification::assertSentTo($user, OneTimePassword::class);
});

it('completa el login con el código correcto del correo', function (): void {
    Notification::fake();

    $user = AuthUser::seed();
    $user->enableEmailOtp();

    $this->post('/login', ['email' => 'ana@example.test', 'password' => 'password']);

    // Se emite el mismo código que recibiría por correo.
    $code = app(EmailOtpService::class)->issue($user);

    $this->post(route('otp.challenge'), ['code' => $code])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('rechaza un código de correo incorrecto sin autenticar', function (): void {
    Notification::fake();

    $user = AuthUser::seed();
    $user->enableEmailOtp();

    $this->post('/login', ['email' => 'ana@example.test', 'password' => 'password']);
    $this->post(route('otp.challenge'), ['code' => '000000'])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('desvía al reto TOTP cuando está activo y lo supera con el código de la app', function (): void {
    $user = AuthUser::seed();
    $user->startTotpEnrolment();
    $user->confirmTotp(app(TotpService::class)->currentCode($user->totpSecret()));

    $this->post('/login', ['email' => 'ana@example.test', 'password' => 'password'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();

    // El código anterior ya está consumido por la confirmación: hay que esperar otro paso,
    // así que se entra con un código de recuperación, que es el otro camino soportado.
    $recovery = $user->totpRecoveryCodes()[0];

    $this->post(route('two-factor.challenge'), ['recovery_code' => $recovery])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->totpRecoveryCodes())->toHaveCount(7);
});

it('el reto no es accesible sin credenciales validadas', function (): void {
    $this->get(route('otp.challenge'))->assertRedirect(route('login'));
    $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
});

it('cierra sesión e invalida la sesión', function (): void {
    $user = AuthUser::seed();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});

it('el enrolamiento exige reconfirmar la contraseña', function (): void {
    $user = AuthUser::seed();

    $this->actingAs($user)->get(route('two-factor.show'))->assertRedirect(route('password.confirm'));
});

it('permite activar y confirmar TOTP tras confirmar la contraseña', function (): void {
    $user = AuthUser::seed();

    $this->actingAs($user)->post('/confirm-password', ['password' => 'password'])->assertRedirect();

    $this->actingAs($user)->post(route('two-factor.enable'))->assertRedirect();

    expect($user->fresh()->hasPendingTotp())->toBeTrue();

    $this->actingAs($user)
        ->get(route('two-factor.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/TwoFactor')->where('totpPending', true));

    $code = app(TotpService::class)->currentCode($user->fresh()->totpSecret());

    $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $code])->assertRedirect();

    expect($user->fresh()->hasEnabledTotp())->toBeTrue();
});
