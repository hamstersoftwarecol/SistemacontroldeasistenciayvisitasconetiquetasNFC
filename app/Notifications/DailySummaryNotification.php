<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailySummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{date: string, total: int, present: int, late: int, absent: int, visits: int, open_complaints: int, absent_names: list<string>, late_names: list<string>}  $summary
     */
    public function __construct(public array $summary) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->summary;

        $mail = (new MailMessage)
            ->subject("Resumen de asistencia {$s['date']}")
            ->greeting("Resumen del {$s['date']}")
            ->line("Personal activo: {$s['total']}")
            ->line("Presentes: {$s['present']} · Tarde: {$s['late']} · Ausentes: {$s['absent']}")
            ->line("Visitas registradas: {$s['visits']} · Quejas abiertas: {$s['open_complaints']}");

        if ($s['late_names'] !== []) {
            $mail->line('Llegaron tarde: '.implode(', ', $s['late_names']));
        }

        if ($s['absent_names'] !== []) {
            $mail->line('Ausentes: '.implode(', ', $s['absent_names']));
        }

        return $mail
            ->action('Ver informe', route('reports.attendance'))
            ->salutation(Setting::get('company_name'));
    }
}
