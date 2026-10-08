<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ReportsAndBackupsTest extends TestCase
{
    use RefreshDatabase;

    private function attendanceData(): User
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create(['name' => 'Elena Empleada', 'created_at' => now()->subMonth()]);
        $location = Location::factory()->create(['name' => 'Recepción']);
        $service = app(AttendanceService::class);

        $this->travelTo(today()->setTime(8, 30));
        $service->registerTap($employee, $location);
        $result = $service->registerTap($employee, $location, 'nfc', [], now()->addHours(8));
        $result->scan->update(['comment' => '=HYPERLINK("http://malo")']);
        $this->travelBack();

        return $admin;
    }

    public function test_attendance_report_renders_and_exports_csv(): void
    {
        $admin = $this->attendanceData();

        $this->actingAs($admin)->get(route('reports.attendance'))->assertOk()->assertSee('Elena Empleada');

        $csv = $this->actingAs($admin)->get(route('reports.attendance', ['exportar' => 'csv', 'tabla' => 'Detalle']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Elena Empleada', $csv);
        $this->assertStringContainsString('Fecha;Empleado;Departamento;Entrada', $csv);
    }

    public function test_visits_csv_neutralizes_formulas(): void
    {
        $admin = $this->attendanceData();

        $csv = $this->actingAs($admin)->get(route('reports.visits', ['exportar' => 'csv', 'tabla' => 'Detalle', 'desde' => today()->toDateString()]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_reports_export_to_xlsx_with_multiple_sheets(): void
    {
        $admin = $this->attendanceData();

        $response = $this->actingAs($admin)->get(route('reports.attendance', ['exportar' => 'xlsx']))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertStringContainsString('name="Resumen"', $zip->getFromName('xl/workbook.xml'));
        $this->assertStringContainsString('Elena Empleada', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet2.xml'));
        $zip->close();

        $this->actingAs($admin)->get(route('reports.complaints', ['exportar' => 'xlsx']))->assertOk();
        $this->actingAs($admin)->get(route('reports.visits', ['exportar' => 'xlsx']))->assertOk();
    }

    public function test_backup_creates_zip_and_uploads_to_google_drive(): void
    {
        // Las copias necesitan una base SQLite en archivo (no en memoria).
        $file = sys_get_temp_dir().'/nfc-backup-test-'.uniqid().'.sqlite';
        touch($file);
        config([
            'database.connections.sqlite_file' => array_merge(config('database.connections.sqlite'), ['database' => $file]),
            'database.default' => 'sqlite_file',
        ]);
        $this->artisan('migrate', ['--force' => true, '--database' => 'sqlite_file']);

        try {
            $this->runBackupScenario();
        } finally {
            config(['database.default' => 'sqlite']);
            DB::purge('sqlite_file');
            @unlink($file);
        }
    }

    private function runBackupScenario(): void
    {
        Setting::flush();

        Storage::fake('local');
        Storage::disk('local')->put('scans/evidencia.jpg', 'foto');
        User::factory()->create(['name' => 'Usuario respaldado']);

        Setting::setMany([
            'gdrive_enabled' => true,
            'gdrive_client_id' => 'client-id',
            'gdrive_client_secret' => 'client-secret',
            'gdrive_refresh_token' => 'refresh-token',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'expires_in' => 3600]),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response([], 200, ['Location' => 'https://upload.example.test/session']),
            'www.googleapis.com/drive/v3/files*' => Http::response(['id' => 'folder-1']),
            'upload.example.test/*' => Http::response(['id' => 'drive-file-1']),
        ]);

        $backup = app(BackupService::class)->run('manual');

        $this->assertSame('success', $backup->status, (string) $backup->error);
        $this->assertSame('uploaded', $backup->drive_status, (string) $backup->error);
        $this->assertSame('drive-file-1', $backup->drive_file_id);
        $this->assertSame('folder-1', Setting::get('gdrive_folder_id'));

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($backup->path()));
        $this->assertNotFalse($zip->getFromName('database.sqlite'));
        $this->assertSame('foto', $zip->getFromName('archivos/scans/evidencia.jpg'));
        $this->assertSame('sqlite', json_decode($zip->getFromName('manifest.json'), true)['database_driver']);
        $zip->close();

        // La copia restaurable contiene los datos.
        $restored = tempnam(sys_get_temp_dir(), 'restore');
        $zip->open(Storage::disk('local')->path($backup->path()));
        file_put_contents($restored, $zip->getFromName('database.sqlite'));
        $zip->close();
        $this->assertStringContainsString('Usuario respaldado', (string) (new \PDO('sqlite:'.$restored))->query('select name from users')->fetchColumn());

        Http::assertSent(fn ($request) => $request->url() === 'https://upload.example.test/session' && $request->method() === 'PUT');

        // Retención: solo se conserva la cantidad configurada.
        Setting::setMany(['backup_retention' => 1, 'gdrive_enabled' => false]);
        $second = app(BackupService::class)->run('scheduled');
        $this->assertSame(1, Backup::count());
        $this->assertTrue($second->is(Backup::sole()));
        Storage::disk('local')->assertMissing($backup->path());

        @unlink($restored);
    }

    public function test_google_drive_oauth_callback_validates_state(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::setMany(['gdrive_client_id' => 'cid', 'gdrive_client_secret' => 'secret']);

        $redirect = $this->actingAs($admin)->get(route('backups.google.redirect'))->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $redirect->headers->get('Location'));
        $this->assertStringContainsString('drive.file', urldecode($redirect->headers->get('Location')));

        $this->actingAs($admin)->get(route('backups.google.callback', ['state' => 'falso', 'code' => 'x']))
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('error');

        $this->assertFalse(Setting::bool('gdrive_enabled'));
    }
}
