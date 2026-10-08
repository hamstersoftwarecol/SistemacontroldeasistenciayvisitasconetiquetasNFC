<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestMailNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Correo de prueba - '.Setting::get('company_name'))
            ->greeting('¡Funciona!')
            ->line('La configuración SMTP de AsistenciaNFC está enviando correos correctamente.')
            ->salutation(Setting::get('company_name'));
    }
}
