<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LateArrivalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Attendance $attendance) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $attendance = $this->attendance->loadMissing('user', 'checkInLocation');

        return (new MailMessage)
            ->subject("Llegada tarde: {$attendance->user->name}")
            ->greeting('Aviso de llegada tarde')
            ->line("{$attendance->user->name} registró su entrada a las {$attendance->check_in_at->format('H:i')} ({$attendance->late_minutes} min tarde).")
            ->line('Ubicación: '.($attendance->checkInLocation?->name ?? '—'))
            ->action('Ver panel del equipo', route('team.index'))
            ->salutation(Setting::get('company_name'));
    }
}
