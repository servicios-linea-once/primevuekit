<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Entrega el código de un solo uso del segundo factor.
 *
 * Va en cola: el envío no debe bloquear la respuesta del intento de login. Si el
 * worker está parado el código no llegará, así que el comando lo advierte.
 */
class OneTimePassword extends Notification implements ShouldQueue
{
    public function __construct(
        private readonly string $code,
        private readonly int $ttl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) ceil($this->ttl / 60);

        return (new MailMessage)
            ->subject(__('Código de verificación'))
            ->line(__('Introduce este código para completar el inicio de sesión:'))
            ->line($this->code)
            ->line(__('Caduca en :minutes minutos y sólo puede usarse una vez.', ['minutes' => $minutes]))
            ->line(__('Si no has intentado iniciar sesión, ignora este mensaje y cambia tu contraseña.'));
    }
}
