<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComplaintCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Complaint $complaint) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $complaint = $this->complaint->loadMissing('location', 'user');

        return (new MailMessage)
            ->subject("Nueva queja {$complaint->code}: {$complaint->subject}")
            ->greeting('Se registró una nueva queja')
            ->line("**{$complaint->subject}**")
            ->line("Categoría: {$complaint->categoryLabel()} · Prioridad: {$complaint->priorityLabel()}")
            ->line('Ubicación: '.($complaint->location?->name ?? '—'))
            ->line('Reportada por: '.$complaint->reporterName())
            ->line(str($complaint->description)->limit(400)->toString())
            ->action('Gestionar queja', route('complaints.show', $complaint))
            ->salutation(Setting::get('company_name'));
    }
}
