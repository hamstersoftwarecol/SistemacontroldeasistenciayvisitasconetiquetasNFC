<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backup:run {--trigger=scheduled : Origen de la copia (scheduled|manual)}')]
#[Description('Crea una copia de seguridad (base de datos + archivos) y la sube a Google Drive si está conectado')]
class RunBackup extends Command
{
    public function handle(BackupService $backups): int
    {
        $this->info('Creando copia de seguridad…');

        $backup = $backups->run($this->option('trigger') === 'manual' ? 'manual' : 'scheduled');

        if ($backup->status === 'failed') {
            $this->error('La copia falló: '.$backup->error);

            return self::FAILURE;
        }

        $this->info("Copia creada: {$backup->filename} ({$backup->sizeLabel()})");
        $this->line('Google Drive: '.match ($backup->drive_status) {
            'uploaded' => 'subida correctamente',
            'failed' => 'error - '.$backup->error,
            default => 'no configurado',
        });

        return self::SUCCESS;
    }
}
