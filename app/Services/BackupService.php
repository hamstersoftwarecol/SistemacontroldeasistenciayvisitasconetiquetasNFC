<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Setting;
use App\Notifications\BackupFailedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Copias de seguridad: base de datos + archivos subidos, comprimidos en ZIP y opcionalmente enviados a Google Drive.
 */
class BackupService
{
    /** Carpetas del disco local que también se respaldan (fotos de evidencia y adjuntos). */
    private const FILE_DIRECTORIES = ['scans', 'complaints'];

    public function __construct(
        private readonly GoogleDriveService $drive,
        private readonly Notifier $notifier,
    ) {}

    public function run(string $trigger = 'manual'): Backup
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(Backup::DIRECTORY);

        $backup = Backup::create([
            'filename' => 'respaldo-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(4)).'.zip',
            'status' => 'running',
            'trigger' => $trigger,
        ]);

        $tempDir = storage_path('app/backup-tmp-'.Str::random(8));
        @mkdir($tempDir, 0700, true);

        try {
            $dump = $this->dumpDatabase($tempDir);
            $zipPath = $disk->path($backup->path());
            $this->zip($zipPath, $dump);

            $backup->forceFill(['status' => 'success', 'size' => filesize($zipPath) ?: 0])->save();
        } catch (Throwable $e) {
            report($e);
            $backup->forceFill(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 1000)])->save();
            $this->notifyFailure($backup);

            return $backup;
        } finally {
            $this->deleteDirectory($tempDir);
        }

        $this->uploadToDrive($backup);
        $this->prune();

        return $backup->refresh();
    }

    public function uploadToDrive(Backup $backup): void
    {
        if (! $this->drive->isEnabled()) {
            $backup->forceFill(['drive_status' => 'skipped'])->save();

            return;
        }

        try {
            $fileId = $this->drive->upload(Storage::disk('local')->path($backup->path()), $backup->filename);
            $backup->forceFill(['drive_status' => 'uploaded', 'drive_file_id' => $fileId])->save();
        } catch (Throwable $e) {
            report($e);
            $backup->forceFill([
                'drive_status' => 'failed',
                'error' => Str::limit('Google Drive: '.$e->getMessage(), 1000),
            ])->save();
            $this->notifyFailure($backup);
        }
    }

    public function delete(Backup $backup): void
    {
        Storage::disk('local')->delete($backup->path());

        if ($backup->drive_file_id && $this->drive->isConnected()) {
            rescue(fn () => $this->drive->delete($backup->drive_file_id));
        }

        $backup->delete();
    }

    /**
     * Conserva solo las N copias más recientes (local y Drive).
     */
    public function prune(): void
    {
        $keep = max(1, Setting::int('backup_retention', 14));

        Backup::query()
            ->latest('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get()
            ->each(fn (Backup $old) => $this->delete($old));
    }

    /**
     * @return array{path: string, name: string}
     */
    private function dumpDatabase(string $dir): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $target = $dir.'/database.sqlite';
            $source = $connection->getDatabaseName();

            if ($source === ':memory:') {
                throw new RuntimeException('No se puede respaldar una base de datos en memoria.');
            }

            try {
                // Copia consistente aunque haya escrituras en curso.
                $connection->statement('VACUUM INTO ?', [$target]);
            } catch (Throwable) {
                if (! @copy($source, $target)) {
                    throw new RuntimeException('No se pudo copiar el archivo SQLite.');
                }
            }

            return ['path' => $target, 'name' => 'database.sqlite'];
        }

        if (in_array($driver, ['mysql', 'mariadb'], true) && ($binary = (new ExecutableFinder)->find('mysqldump'))) {
            $target = $dir.'/database.sql';
            $config = $connection->getConfig();

            $process = new Process([
                $binary,
                '--single-transaction', '--routines', '--no-tablespaces',
                '--host='.($config['host'] ?? '127.0.0.1'),
                '--port='.($config['port'] ?? 3306),
                '--user='.($config['username'] ?? 'root'),
                '--result-file='.$target,
                $config['database'],
            ], null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')], null, 600);
            $process->mustRun();

            return ['path' => $target, 'name' => 'database.sql'];
        }

        // Alternativa portable: exportar cada tabla a JSON.
        $target = $dir.'/database.json';
        $handle = fopen($target, 'w');
        fwrite($handle, '{');
        $first = true;

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            fwrite($handle, ($first ? '' : ',').json_encode($table).':[');
            $firstRow = true;
            foreach (DB::table($table)->cursor() as $row) {
                fwrite($handle, ($firstRow ? '' : ',').json_encode($row, JSON_UNESCAPED_UNICODE));
                $firstRow = false;
            }
            fwrite($handle, ']');
            $first = false;
        }

        fwrite($handle, '}');
        fclose($handle);

        return ['path' => $target, 'name' => 'database.json'];
    }

    /**
     * @param  array{path: string, name: string}  $dump
     */
    private function zip(string $zipPath, array $dump): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión zip de PHP es necesaria para las copias de seguridad.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP.');
        }

        $zip->addFile($dump['path'], $dump['name']);

        $disk = Storage::disk('local');
        foreach (self::FILE_DIRECTORIES as $directory) {
            foreach ($disk->allFiles($directory) as $file) {
                $zip->addFile($disk->path($file), 'archivos/'.$file);
            }
        }

        $zip->addFromString('manifest.json', json_encode([
            'app' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'database_driver' => DB::connection()->getDriverName(),
            'database_file' => $dump['name'],
            'timezone' => config('app.timezone'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->close();
    }

    private function notifyFailure(Backup $backup): void
    {
        if (Setting::bool('notify_backup_failure')) {
            $this->notifier->toPermission('backups.manage', new BackupFailedNotification($backup));
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }
}
