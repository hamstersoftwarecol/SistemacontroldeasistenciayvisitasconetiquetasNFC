<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\ComplaintUpdate;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComplaintUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Complaint $complaint, public ComplaintUpdate $update, public bool $forStaff = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $complaint = $this->complaint;
        $mail = (new MailMessage)
            ->subject($this->forStaff ? "Nueva respuesta en la queja {$complaint->code}" : "Actualización de tu queja {$complaint->code}")
            ->greeting($this->forStaff ? 'Hay una nueva respuesta' : 'Tu queja tiene novedades')
            ->line("**{$complaint->subject}**")
            ->line("Estado actual: {$complaint->statusLabel()}");

        if (filled($this->update->body)) {
            $mail->line('Respuesta: '.$this->update->body);
        }

        if ($complaint->user_id || $this->forStaff) {
            $mail->action('Ver queja', route('complaints.show', $complaint));
        }

        return $mail->salutation(Setting::get('company_name'));
    }
}
