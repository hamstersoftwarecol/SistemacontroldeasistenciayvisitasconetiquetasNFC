<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Location;
use App\Models\Scan;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LateArrivalNotification;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NfcAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $time): void
    {
        $this->travelTo(now()->setTimeFromTimeString($time));
    }

    public function test_first_tap_is_check_in_and_last_tap_is_check_out(): void
    {
        $user = User::factory()->create();
        $entrance = Location::factory()->create(['name' => 'Entrada']);
        $room = Location::factory()->create(['name' => 'Habitación 101']);

        $this->at('07:55');
        $this->actingAs($user)->get(route('tap', $entrance->token))->assertRedirect();

        $attendance = Attendance::sole();
        $this->assertSame('07:55', $attendance->check_in_at->format('H:i'));
        $this->assertNull($attendance->check_out_at);
        $this->assertFalse($attendance->is_late);
        $this->assertSame(Scan::TYPE_CHECK_IN, Scan::sole()->type);

        $this->at('10:30');
        $this->actingAs($user)->get(route('tap', $room->token));

        $this->at('17:05');
        $this->actingAs($user)->get(route('tap', $entrance->token));

        $attendance->refresh();
        $this->assertSame('17:05', $attendance->check_out_at->format('H:i'));
        $this->assertSame(3, $attendance->scans_count);
        $this->assertSame(9 * 60 + 10, $attendance->worked_minutes);
        $this->assertSame($entrance->id, $attendance->check_out_location_id);

        // Solo el último toque queda como salida; los intermedios son visitas.
        $this->assertSame(
            [Scan::TYPE_CHECK_IN, Scan::TYPE_VISIT, Scan::TYPE_CHECK_OUT],
            Scan::orderBy('scanned_at')->pluck('type')->all()
        );
    }

    public function test_repeated_tap_within_cooldown_is_ignored(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();

        $this->at('08:00:00');
        $this->actingAs($user)->get(route('tap', $location->token));
        $this->at('08:00:20');
        $this->actingAs($user)->get(route('tap', $location->token))->assertSessionHas('tap.duplicate', true);

        $this->assertSame(1, Scan::count());
        $this->assertSame(1, Attendance::sole()->scans_count);
    }

    public function test_late_arrival_is_detected_with_grace_period_and_notifies_supervisors(): void
    {
        Notification::fake();
        Setting::setMany(['work_start' => '08:00', 'grace_minutes' => 10, 'notify_late' => true]);

        $supervisor = User::factory()->supervisor()->create();
        $onTime = User::factory()->create();
        $late = User::factory()->create();
        $location = Location::factory()->create();

        $this->at('08:09');
        app(AttendanceService::class)->registerTap($onTime, $location);
        $this->at('08:25');
        app(AttendanceService::class)->registerTap($late, $location);

        $this->assertFalse(Attendance::where('user_id', $onTime->id)->sole()->is_late);
        $record = Attendance::where('user_id', $late->id)->sole();
        $this->assertTrue($record->is_late);
        $this->assertSame(25, $record->late_minutes);

        Notification::assertSentTo($supervisor, LateArrivalNotification::class);
        Notification::assertSentTimes(LateArrivalNotification::class, 1);
    }

    public function test_personal_shift_overrides_general_schedule(): void
    {
        $user = User::factory()->create(['shift_start' => '06:00', 'shift_end' => '14:00']);
        $location = Location::factory()->create();

        $this->at('06:30');
        app(AttendanceService::class)->registerTap($user, $location);

        $this->assertSame(30, Attendance::sole()->late_minutes);
    }

    public function test_night_shift_taps_after_midnight_belong_to_previous_day(): void
    {
        $user = User::factory()->create(['shift_start' => '22:00', 'shift_end' => '06:00']);
        $location = Location::factory()->create();
        $service = app(AttendanceService::class);

        $this->travelTo(now()->startOfDay()->setTime(21, 55));
        $service->registerTap($user, $location);
        $workDate = today()->toDateString();

        $this->travelTo(now()->addDay()->startOfDay()->setTime(6, 5));
        $service->registerTap($user, $location);

        $attendance = Attendance::sole();
        $this->assertSame($workDate, $attendance->date->toDateString());
        $this->assertSame(8 * 60 + 10, $attendance->worked_minutes);
    }

    public function test_guest_tap_redirects_to_login_and_registers_after_login(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();

        $this->get(route('tap', $location->token))->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('tap', $location->token));

        $this->get(route('tap', $location->token))->assertRedirect();
        $this->assertSame(1, Scan::where('user_id', $user->id)->count());
    }

    public function test_cross_site_request_requires_confirmation(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();

        $this->actingAs($user)
            ->withHeader('Sec-Fetch-Site', 'cross-site')
            ->get(route('tap', $location->token))
            ->assertOk()
            ->assertSee('Registrar ahora');

        $this->assertSame(0, Scan::count());

        $this->actingAs($user)->post(route('tap.store', $location->token), ['source' => 'nfc'])->assertRedirect();
        $this->assertSame(1, Scan::count());
    }

    public function test_web_nfc_scan_returns_json_and_stores_tag_uid(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create(['name' => 'Bodega']);

        $this->actingAs($user)
            ->postJson(route('tap.store', $location->token), ['source' => 'web_nfc', 'tag_uid' => '04:a1:b2:c3'])
            ->assertOk()
            ->assertJson(['ok' => true, 'type' => 'check_in', 'location' => 'Bodega', 'headline' => 'Entrada registrada']);

        $scan = Scan::sole();
        $this->assertSame('web_nfc', $scan->source);
        $this->assertSame('04A1B2C3', $scan->tag_uid);
    }

    public function test_unknown_or_disabled_tags_are_rejected(): void
    {
        $user = User::factory()->create();
        $disabled = Location::factory()->inactive()->create();

        $this->actingAs($user)->get(route('tap', 'tokenquenoexiste1234567890'))->assertNotFound();
        $this->actingAs($user)->get(route('tap', $disabled->token))->assertNotFound()->assertSee('desactivada');
        $this->actingAs($user)->postJson(route('tap.store', $disabled->token))->assertNotFound()->assertJson(['ok' => false]);

        $this->assertSame(0, Scan::count());
    }

    public function test_employee_can_add_comment_and_photo_to_receipt(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $location = Location::factory()->create();
        $scan = app(AttendanceService::class)->registerTap($user, $location)->scan;

        $this->actingAs($user)->get(route('scans.receipt', $scan))->assertOk()->assertSee('Código de verificación');

        $this->actingAs($user)->patch(route('scans.comment', $scan), [
            'comment' => 'Habitación lista',
            'photo' => UploadedFile::fake()->image('evidencia.jpg'),
        ])->assertSessionHasNoErrors();

        $scan->refresh();
        $this->assertSame('Habitación lista', $scan->comment);
        Storage::disk('local')->assertExists($scan->photo_path);
        $this->actingAs($user)->get(route('scans.photo', $scan))->assertOk();

        // Otro empleado no puede ver ni comentar el comprobante.
        $this->actingAs($other)->get(route('scans.receipt', $scan))->assertForbidden();
        $this->actingAs($other)->get(route('scans.photo', $scan))->assertForbidden();
        $this->actingAs($other)->patch(route('scans.comment', $scan), ['comment' => 'x'])->assertForbidden();
    }

    public function test_kiosk_registers_by_card_uid_or_employee_code(): void
    {
        $operator = User::factory()->supervisor()->create();
        $employee = User::factory()->create(['nfc_card_uid' => '04:AB:CD:EF', 'employee_code' => 'EMP-9']);
        $location = Location::factory()->create();

        $this->actingAs($operator)
            ->postJson(route('kiosk.store'), ['location_id' => $location->id, 'uid' => '04abcdef'])
            ->assertOk()
            ->assertJson(['user' => $employee->name, 'type' => 'check_in']);

        $this->travel(5)->minutes();

        $this->actingAs($operator)
            ->postJson(route('kiosk.store'), ['location_id' => $location->id, 'employee_code' => 'EMP-9'])
            ->assertOk()
            ->assertJson(['type' => 'check_out']);

        $this->actingAs($operator)
            ->postJson(route('kiosk.store'), ['location_id' => $location->id, 'uid' => 'FFFFFFFF'])
            ->assertNotFound();

        $this->assertSame(2, Scan::where('user_id', $employee->id)->where('source', 'kiosk')->count());
    }

    public function test_tag_writer_marks_location_as_written_and_locked(): void
    {
        $admin = User::factory()->admin()->create();
        $location = Location::factory()->create();

        $this->actingAs($admin)->postJson(route('nfc.written', $location), ['tag_uid' => '04:11:22:33'])
            ->assertOk()->assertJsonPath('location.tag_uid', '04112233');
        $this->actingAs($admin)->postJson(route('nfc.locked', $location))->assertOk()->assertJsonPath('location.is_locked', true);

        $this->actingAs($admin)->postJson(route('nfc.identify'), ['text' => $location->tapUrl()])
            ->assertOk()->assertJson(['found' => true, 'location' => ['id' => $location->id]]);

        // Regenerar el enlace invalida la etiqueta anterior.
        $oldToken = $location->token;
        $this->actingAs($admin)->post(route('locations.regenerate', $location));
        $this->assertNotSame($oldToken, $location->fresh()->token);
        $this->assertFalse($location->fresh()->is_locked);
        $this->actingAs($admin)->get(route('tap', $oldToken))->assertNotFound();
    }

    public function test_manual_attendance_can_be_created_and_corrected(): void
    {
        Setting::setMany(['work_start' => '08:00', 'grace_minutes' => 5]);
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $location = Location::factory()->create();
        $date = today()->subDay()->toDateString();

        $this->actingAs($admin)->post(route('attendances.store'), [
            'user_id' => $employee->id, 'date' => $date, 'check_in' => '08:20', 'check_out' => '16:00',
            'location_id' => $location->id, 'notes' => 'Olvidó marcar',
        ])->assertSessionHasNoErrors();

        $attendance = Attendance::sole();
        $this->assertTrue($attendance->is_late);
        $this->assertSame(460, $attendance->worked_minutes);
        $this->assertSame(2, $attendance->scans_count);

        $this->actingAs($admin)->put(route('attendances.update', $attendance), ['check_in' => '07:58', 'check_out' => '16:30'])
            ->assertSessionHasNoErrors();

        $attendance->refresh();
        $this->assertFalse($attendance->is_late);
        $this->assertSame(512, $attendance->worked_minutes);

        // No se permiten dos registros para el mismo día.
        $this->actingAs($admin)->post(route('attendances.store'), [
            'user_id' => $employee->id, 'date' => $date, 'check_in' => '09:00', 'location_id' => $location->id,
        ])->assertSessionHasErrors('date');
    }
}
