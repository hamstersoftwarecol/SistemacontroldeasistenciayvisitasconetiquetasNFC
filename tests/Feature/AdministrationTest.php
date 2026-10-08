<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\TestMailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_defaults_and_custom_permissions(): void
    {
        $employee = User::factory()->create();
        $this->assertFalse($employee->can('reports.view'));
        $this->assertTrue($employee->can('chat.use'));

        $supervisor = User::factory()->supervisor()->create();
        $this->assertTrue($supervisor->can('team.view'));
        $this->assertFalse($supervisor->can('settings.manage'));

        $custom = User::factory()->create(['permissions' => ['reports.view']]);
        $this->assertTrue($custom->can('reports.view'));
        $this->assertFalse($custom->can('chat.use'));

        $admin = User::factory()->admin()->create(['permissions' => []]);
        $this->assertTrue($admin->can('backups.manage'));
    }

    public function test_admin_creates_user_with_custom_permissions(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Pedro Pérez',
            'email' => 'pedro@example.com',
            'password' => 'secreta123',
            'role' => 'employee',
            'is_active' => 1,
            'employee_code' => 'EMP-50',
            'nfc_card_uid' => '04:aa:bb',
            'custom_permissions' => 1,
            'permissions' => ['reports.view', 'chat.use'],
        ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

        $user = User::firstWhere('email', 'pedro@example.com');
        $this->assertSame(Role::Employee, $user->role);
        $this->assertSame('04AABB', $user->nfc_card_uid);
        $this->assertSame(['reports.view', 'chat.use'], $user->permissions);
        $this->actingAs($user)->get(route('reports.index'))->assertOk();
        $this->actingAs($user)->get(route('ai.index'))->assertForbidden();
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'employee', 'is_active' => 1,
        ])->assertSessionHasErrors('role');

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'is_active' => 0,
        ])->assertSessionHasErrors('is_active');

        $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_settings_are_saved_and_secrets_are_encrypted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('settings.update'), [
            'section' => 'horario', 'work_start' => '07:30', 'work_end' => '16:30', 'grace_minutes' => 5,
            'working_days' => [1, 2, 3, 4, 5, 6], 'tap_cooldown_seconds' => 30, 'active_window_minutes' => 90,
        ])->assertSessionHasNoErrors();

        $this->assertSame('07:30', Setting::get('work_start'));
        $this->assertSame([1, 2, 3, 4, 5, 6], Setting::workingDays());

        $this->actingAs($admin)->put(route('settings.update'), [
            'section' => 'correo', 'mail_enabled' => 1, 'mail_host' => 'smtp.example.com', 'mail_port' => 587,
            'mail_username' => 'user', 'mail_password' => 'super-secreta', 'mail_encryption' => 'tls', 'mail_from_address' => 'no-reply@example.com',
        ])->assertSessionHasNoErrors();

        $raw = DB::table('settings')->where('key', 'mail_password')->value('value');
        $this->assertNotSame('super-secreta', $raw);
        $this->assertSame('super-secreta', Setting::get('mail_password'));

        // La contraseña nunca se envía al navegador.
        $this->actingAs($admin)->get(route('settings.edit', ['seccion' => 'correo']))->assertDontSee('super-secreta');

        // Dejar el secreto vacío conserva el anterior.
        $this->actingAs($admin)->put(route('settings.update'), [
            'section' => 'correo', 'mail_enabled' => 1, 'mail_host' => 'smtp.example.com', 'mail_port' => 465,
            'mail_password' => '', 'mail_encryption' => 'ssl', 'mail_from_address' => 'no-reply@example.com',
        ])->assertSessionHasNoErrors();
        $this->assertSame('super-secreta', Setting::get('mail_password'));

        $this->actingAs($admin)->put(route('settings.update'), ['section' => 'correo', 'mail_enabled' => 1, 'mail_host' => ''])
            ->assertSessionHasErrors('mail_host');
    }

    public function test_test_email_can_be_sent(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('settings.test-mail'), ['test_email' => 'prueba@example.com'])->assertSessionHas('success');

        Notification::assertSentOnDemand(TestMailNotification::class);
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('nfc:admin', ['email' => 'jefe@example.com', '--name' => 'Jefe', '--password' => 'clave-segura'])->assertSuccessful();

        $user = User::firstWhere('email', 'jefe@example.com');
        $this->assertTrue($user->isAdmin());
    }
}
