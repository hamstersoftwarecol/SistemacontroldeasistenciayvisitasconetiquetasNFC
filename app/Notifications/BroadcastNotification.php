<?php

namespace App\Notifications;

use App\Models\Broadcast;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BroadcastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Broadcast $broadcast) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $broadcast = $this->broadcast->loadMissing('sender');
        $prefix = $broadcast->priority === 'normal' ? '' : '['.$broadcast->priorityLabel().'] ';

        return (new MailMessage)
            ->subject($prefix.$broadcast->title)
            ->greeting($broadcast->title)
            ->line($broadcast->body)
            ->line('Enviado por: '.($broadcast->sender?->name ?? 'Administración'))
            ->action('Ver en la plataforma', route('broadcasts.show', $broadcast))
            ->salutation(Setting::get('company_name'));
    }
}
