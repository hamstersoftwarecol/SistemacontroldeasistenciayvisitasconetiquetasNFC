<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComplaintAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Complaint $complaint) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $complaint = $this->complaint->loadMissing('location');

        return (new MailMessage)
            ->subject("Te asignaron la queja {$complaint->code}")
            ->greeting('Nueva queja asignada')
            ->line("**{$complaint->subject}**")
            ->line("Prioridad: {$complaint->priorityLabel()} · Ubicación: ".($complaint->location?->name ?? '—'))
            ->action('Ver queja', route('complaints.show', $complaint))
            ->salutation(Setting::get('company_name'));
    }
}
