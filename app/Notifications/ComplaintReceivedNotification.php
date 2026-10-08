<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComplaintReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Complaint $complaint) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Recibimos tu queja {$this->complaint->code}")
            ->greeting('¡Gracias por avisarnos!')
            ->line("Registramos tu reporte «{$this->complaint->subject}» con el código {$this->complaint->code}.")
            ->line('Nuestro equipo lo revisará y te escribiremos a este correo cuando haya novedades.')
            ->salutation(Setting::get('company_name'));
    }
}
