<?php

namespace App\Notifications;

use App\Models\Backup;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Backup $backup) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Falló la copia de seguridad')
            ->greeting('Problema con la copia de seguridad')
            ->line("Archivo: {$this->backup->filename}")
            ->line('Error: '.($this->backup->error ?? 'desconocido'))
            ->action('Revisar copias de seguridad', route('backups.index'))
            ->salutation(Setting::get('company_name'));
    }
}
