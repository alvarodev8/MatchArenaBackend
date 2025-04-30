<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends ResetPasswordNotification
{
    public function toMail($notifiable)
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $frontendUrl = sprintf(
            '%s/reset-password?token=%s&email=%s',
            env('FRONTEND_URL', 'http://localhost:4200'),
            $this->token,
            urlencode($notifiable->getEmailForPasswordReset())
        );

        return (new MailMessage)
            ->subject('Restablecimiento de Contraseña')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer Contraseña', $frontendUrl)
            ->line('Si no solicitaste este cambio, puedes ignorar este correo.')
            ->line('Este enlace expirará en ' . config('auth.passwords.users.expire') . ' minutos.')
            ->line('Gracias por usar nuestra aplicación!');
    }
}
