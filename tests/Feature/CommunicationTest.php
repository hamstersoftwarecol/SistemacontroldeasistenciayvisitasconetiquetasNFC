<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\Complaint;
use App\Models\Location;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use App\Notifications\ComplaintAssignedNotification;
use App\Notifications\ComplaintCreatedNotification;
use App\Notifications\ComplaintReceivedNotification;
use App\Notifications\ComplaintUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_complaint_notifies_managers_and_status_change_notifies_reporter(): void
    {
        Notification::fake();
        $supervisor = User::factory()->supervisor()->create();
        $employee = User::factory()->create();
        $location = Location::factory()->create();

        $this->actingAs($employee)->post(route('complaints.store'), [
            'subject' => 'Aire acondicionado dañado',
            'description' => 'No enfría desde ayer.',
            'category' => 'maintenance',
            'priority' => 'high',
            'location_id' => $location->id,
        ])->assertRedirect();

        $complaint = Complaint::sole();
        $this->assertSame('QJ-00001', $complaint->code);
        Notification::assertSentTo($supervisor, ComplaintCreatedNotification::class);
        Notification::assertNotSentTo($employee, ComplaintCreatedNotification::class);

        $this->actingAs($supervisor)->patch(route('complaints.update', $complaint), [
            'status' => 'resolved', 'priority' => 'high', 'category' => 'maintenance',
            'assigned_to' => $supervisor->id, 'note' => 'Se cambió el compresor.',
        ])->assertSessionHasNoErrors();

        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertNotNull($complaint->resolved_at);
        $this->assertDatabaseHas('complaint_updates', ['status_from' => 'open', 'status_to' => 'resolved']);
        Notification::assertSentTo($employee, ComplaintUpdatedNotification::class);
    }

    public function test_assignment_notifies_assignee_and_internal_notes_are_hidden(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->create();
        $reporter = User::factory()->create();
        $complaint = Complaint::create(['user_id' => $reporter->id, 'subject' => 'Fuga', 'description' => 'Agua', 'category' => 'maintenance', 'priority' => 'urgent']);

        $this->actingAs($admin)->patch(route('complaints.update', $complaint), [
            'status' => 'in_progress', 'priority' => 'urgent', 'category' => 'maintenance', 'assigned_to' => $technician->id,
        ]);
        Notification::assertSentTo($technician, ComplaintAssignedNotification::class);

        // El responsable asignado puede ver la queja aunque sea empleado.
        $this->actingAs($technician)->get(route('complaints.show', $complaint))->assertOk();

        $this->actingAs($admin)->post(route('complaints.reply', $complaint), ['body' => 'Revisar presupuesto', 'is_internal' => 1]);
        $this->actingAs($reporter)->get(route('complaints.show', $complaint))->assertOk()->assertDontSee('Revisar presupuesto');
        $this->actingAs($admin)->get(route('complaints.show', $complaint))->assertSee('Revisar presupuesto');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('complaints.show', $complaint))->assertForbidden();
        $this->actingAs($outsider)->get(route('complaints.index'))->assertDontSee('Fuga');
    }

    public function test_public_complaint_form_creates_complaint_and_confirms_by_email(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $location = Location::factory()->create(['name' => 'Habitación 305']);

        $this->get(route('public.complaints.create', $location->public_token))->assertOk()->assertSee('Habitación 305');

        $this->post(route('public.complaints.store', $location->public_token), [
            'reporter_name' => 'Huésped',
            'reporter_email' => 'huesped@example.com',
            'category' => 'cleaning',
            'subject' => 'Baño sucio',
            'description' => 'El baño no fue aseado.',
        ])->assertSessionHas('status');

        $complaint = Complaint::sole();
        $this->assertNull($complaint->user_id);
        $this->assertSame($location->id, $complaint->location_id);
        Notification::assertSentTo($admin, ComplaintCreatedNotification::class);
        Notification::assertSentOnDemand(ComplaintReceivedNotification::class, fn ($n, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'huesped@example.com');

        // El enlace público no sirve para marcar asistencia y viceversa.
        $this->get(route('public.complaints.create', $location->token))->assertNotFound();

        Setting::set('public_complaints', false);
        $this->get(route('public.complaints.create', $location->public_token))->assertNotFound();
    }

    public function test_public_complaint_honeypot_blocks_bots(): void
    {
        $location = Location::factory()->create();

        $this->post(route('public.complaints.store', $location->public_token), [
            'reporter_name' => 'Bot', 'category' => 'general', 'subject' => 'Spam', 'description' => 'Spam', 'website' => 'http://spam.test',
        ]);

        $this->assertSame(0, Complaint::count());
    }

    public function test_team_chat_general_channel_and_direct_messages(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $beto = User::factory()->create(['name' => 'Beto']);

        $this->actingAs($ana)->postJson(route('chat.send'), ['canal' => 'general', 'body' => 'Hola equipo'])->assertCreated();
        $this->actingAs($ana)->postJson(route('chat.send'), ['canal' => (string) $beto->id, 'body' => '¿Revisaste la 101?'])->assertCreated();

        $contacts = collect($this->actingAs($beto)->getJson(route('chat.contacts'))->json('contacts'))->keyBy('id');
        $this->assertSame(1, $contacts['general']['unread']);
        $this->assertSame(1, $contacts[(string) $ana->id]['unread']);

        $messages = $this->actingAs($beto)->getJson(route('chat.messages', ['canal' => $ana->id]))->json('messages');
        $this->assertCount(1, $messages);
        $this->assertFalse($messages[0]['mine']);
        $this->assertNotNull(Message::where('recipient_id', $beto->id)->sole()->read_at);

        $lastId = $messages[0]['id'];
        $this->actingAs($ana)->postJson(route('chat.send'), ['canal' => (string) $beto->id, 'body' => 'Gracias']);
        $this->assertCount(1, $this->actingAs($beto)->getJson(route('chat.messages', ['canal' => $ana->id, 'after' => $lastId]))->json('messages'));

        // Un tercero no ve los mensajes directos ajenos.
        $carla = User::factory()->create();
        $this->assertCount(0, $this->actingAs($carla)->getJson(route('chat.messages', ['canal' => $ana->id]))->json('messages'));
        $this->assertCount(1, $this->actingAs($carla)->getJson(route('chat.messages', ['canal' => 'general']))->json('messages'));
    }

    public function test_broadcast_with_email_reaches_the_audience(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($admin)->post(route('broadcasts.store'), [
            'title' => 'Simulacro de evacuación', 'body' => 'Viernes 10am', 'audience' => 'employee', 'priority' => 'urgent', 'send_email' => 1,
        ])->assertRedirect();

        $broadcast = Broadcast::sole();
        Notification::assertSentTo($employee, BroadcastNotification::class);
        Notification::assertNotSentTo($supervisor, BroadcastNotification::class);

        $this->actingAs($employee)->get(route('dashboard'))->assertSee('Simulacro de evacuación');
        $this->actingAs($employee)->get(route('broadcasts.show', $broadcast))->assertOk();
        $this->assertSame(0, Broadcast::query()->unreadBy($employee)->count());

        $this->actingAs($supervisor)->get(route('broadcasts.show', $broadcast))->assertForbidden();
    }
}
