<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Broadcast;
use App\Models\Complaint;
use App\Models\Location;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): array
    {
        $admin = User::factory()->admin()->create(['name' => 'Ana Admin']);
        $employee = User::factory()->create(['name' => 'Elena Empleada', 'department' => 'Limpieza']);
        $location = Location::factory()->create(['name' => 'Habitación 101']);

        $tap = app(AttendanceService::class)->registerTap($employee, $location, 'nfc', [], now()->subHour());
        app(AttendanceService::class)->registerTap($employee, $location, 'nfc', [], now()->subMinutes(5));

        $complaint = Complaint::create([
            'user_id' => $employee->id, 'subject' => 'Ducha sin agua caliente', 'description' => 'Desde ayer',
            'category' => 'maintenance', 'priority' => 'high', 'location_id' => $location->id,
        ]);
        $broadcast = Broadcast::create(['user_id' => $admin->id, 'title' => 'Reunión general', 'body' => 'Mañana 9am', 'audience' => 'all', 'priority' => 'important']);

        return compact('admin', 'employee', 'location', 'tap', 'complaint', 'broadcast');
    }

    public function test_admin_can_open_every_page(): void
    {
        $d = $this->seedData();
        $attendance = Attendance::first();

        // La difusión importante sin leer aparece como aviso destacado.
        $this->actingAs($d['admin'])->get(route('dashboard'))->assertSee('Reunión general')->assertSee('Elena Empleada');

        $urls = [
            route('dashboard'), route('my.tracker'), route('my.history'), route('my.history', ['mes' => '2026-01']),
            route('team.index'), route('attendances.index'), route('attendances.show', $attendance),
            route('attendances.create'), route('attendances.edit', $attendance), route('scans.index'),
            route('scans.receipt', $d['tap']->scan), route('locations.index'), route('locations.create'),
            route('locations.show', $d['location']), route('locations.edit', $d['location']),
            route('nfc.writer'), route('nfc.writer', ['ubicacion' => $d['location']->id]), route('kiosk.index'), route('kiosk.index', ['ubicacion' => $d['location']->id]),
            route('complaints.index'), route('complaints.create'), route('complaints.show', $d['complaint']),
            route('reports.index'), route('reports.attendance'), route('reports.visits'), route('reports.complaints'),
            route('chat.index'), route('broadcasts.index'), route('broadcasts.create'), route('broadcasts.show', $d['broadcast']),
            route('ai.index'), route('users.index'), route('users.create'), route('users.edit', $d['employee']),
            route('backups.index'), route('profile.edit'),
        ];

        foreach (['general', 'horario', 'correo', 'notificaciones', 'ia', 'copias'] as $section) {
            $urls[] = route('settings.edit', ['seccion' => $section]);
        }

        foreach ($urls as $url) {
            $this->assertPageOk($d['admin'], $url);
        }

        $this->actingAs($d['admin'])->get(route('team.status'))->assertOk()->assertJsonPath('summary.present', 1);
        $this->actingAs($d['admin'])->get(route('dashboard.data'))->assertOk()->assertJsonStructure(['summary', 'feed']);
    }

    public function test_employee_sees_personal_pages_only(): void
    {
        $d = $this->seedData();

        foreach ([route('dashboard'), route('my.tracker'), route('my.history'), route('complaints.index'), route('complaints.create'),
            route('complaints.show', $d['complaint']), route('chat.index'), route('broadcasts.index'), route('ai.index'),
            route('scans.receipt', $d['tap']->scan), route('profile.edit')] as $url) {
            $this->assertPageOk($d['employee'], $url);
        }

        foreach ([route('team.index'), route('team.status'), route('attendances.index'), route('scans.index'), route('locations.index'),
            route('nfc.writer'), route('kiosk.index'), route('reports.index'), route('users.index'), route('settings.edit'),
            route('backups.index'), route('broadcasts.create'), route('dashboard.data')] as $url) {
            $this->actingAs($d['employee'])->get($url)->assertForbidden();
        }
    }

    public function test_navigation_only_shows_allowed_sections(): void
    {
        $d = $this->seedData();

        $this->actingAs($d['employee'])->get(route('dashboard'))
            ->assertSee('Mi rastreador')
            ->assertDontSee('Copias de seguridad')
            ->assertDontSee('Equipo en vivo');

        $this->actingAs($d['admin'])->get(route('dashboard'))
            ->assertSee('Copias de seguridad')
            ->assertSee('Equipo en vivo');
    }

    private function assertPageOk(User $user, string $url): void
    {
        $response = $this->actingAs($user)->get($url);

        $this->assertSame(200, $response->status(), "{$url} respondió {$response->status()}: ".($response->exception?->getMessage() ?? ''));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_inactive_user_is_logged_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
