<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Setting;
use App\Services\BackupService;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly GoogleDriveService $drive,
    ) {}

    public function index(): View
    {
        return view('backups.index', [
            'backups' => Backup::query()->latest('id')->paginate(20),
            'drive' => [
                'configured' => $this->drive->isConfigured(),
                'connected' => $this->drive->isConnected(),
                'enabled' => Setting::bool('gdrive_enabled'),
                'account' => Setting::get('gdrive_account'),
                'folder' => Setting::get('gdrive_folder_name'),
                'redirect_uri' => $this->drive->redirectUri(),
            ],
            'schedule' => [
                'enabled' => Setting::bool('backup_enabled'),
                'time' => Setting::get('backup_time'),
                'retention' => Setting::int('backup_retention', 14),
                'last_run' => Cache::get('scheduler_last_run'),
            ],
        ]);
    }

    public function store(): RedirectResponse
    {
        @set_time_limit(600);

        $backup = $this->backups->run('manual');

        return match (true) {
            $backup->status === 'failed' => back()->with('error', 'La copia falló: '.$backup->error),
            $backup->drive_status === 'failed' => back()->with('warning', 'Copia local creada, pero falló la subida a Google Drive: '.$backup->error),
            $backup->drive_status === 'uploaded' => back()->with('success', 'Copia creada y subida a Google Drive.'),
            default => back()->with('success', 'Copia de seguridad creada.'),
        };
    }

    public function download(Backup $backup): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($backup->path()), 404);

        return Storage::disk('local')->download($backup->path(), $backup->filename);
    }

    public function destroy(Backup $backup): RedirectResponse
    {
        $this->backups->delete($backup);

        return back()->with('success', 'Copia eliminada.');
    }

    public function googleRedirect(Request $request): RedirectResponse
    {
        if (! $this->drive->isConfigured()) {
            return redirect()->route('settings.edit', ['seccion' => 'copias'])
                ->with('error', 'Primero guarda el Client ID y el Client Secret de Google en Ajustes.');
        }

        $state = Str::random(40);
        $request->session()->put('gdrive_state', $state);

        return redirect()->away($this->drive->authorizationUrl($state));
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('gdrive_state');

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('backups.index')->with('error', 'La solicitud de Google no es válida. Intenta conectar de nuevo.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('backups.index')->with('error', 'Google canceló la autorización: '.$request->query('error', 'sin código'));
        }

        try {
            $this->drive->connect((string) $request->query('code'));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('backups.index')->with('error', $e->getMessage());
        }

        return redirect()->route('backups.index')->with('success', 'Google Drive conectado. Las copias automáticas se subirán a la carpeta «'.Setting::get('gdrive_folder_name').'».');
    }

    public function googleDisconnect(): RedirectResponse
    {
        $this->drive->disconnect();

        return back()->with('success', 'Google Drive desconectado.');
    }
}
