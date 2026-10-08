<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\ClaudeGateway;
use App\Services\AttendanceService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    /**
     * Sustituye el transporte HTTP del SDK por respuestas simuladas de la API de Claude.
     *
     * @param  list<array<string, mixed>>  $responses
     */
    private function fakeClaude(array $responses): void
    {
        Setting::set('ai_api_key', 'sk-ant-test');

        $mock = new MockHandler(array_map(
            fn (array $body) => new Response(200, ['Content-Type' => 'application/json'], json_encode($body)),
            $responses
        ));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        $gateway = new ClaudeGateway;
        $gateway->transporter = new GuzzleClient(['handler' => $stack]);
        $this->app->instance(ClaudeGateway::class, $gateway);
    }

    /**
     * @param  list<array<string, mixed>>  $content
     * @return array<string, mixed>
     */
    private function message(array $content, string $stopReason): array
    {
        return [
            'id' => 'msg_'.uniqid(),
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => $content,
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBody(int $index): array
    {
        return json_decode((string) $this->history[$index]['request']->getBody(), true);
    }

    private function seedAttendance(): array
    {
        $this->travelTo(today()->setTime(12, 0));

        $employee = User::factory()->create(['name' => 'Elena Empleada']);
        $other = User::factory()->create(['name' => 'Otro Trabajador']);
        $location = Location::factory()->create(['name' => 'Lobby']);

        app(AttendanceService::class)->registerTap($employee, $location, 'nfc', [], now()->subHours(3));
        app(AttendanceService::class)->registerTap($other, $location, 'nfc', [], now()->subHours(2));

        return [$employee, $other];
    }

    public function test_assistant_runs_database_tools_and_answers(): void
    {
        [$employee] = $this->seedAttendance();
        $admin = User::factory()->admin()->create();

        $this->fakeClaude([
            $this->message([
                ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-1'],
                ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'team_status_now', 'input' => new \stdClass],
            ], 'tool_use'),
            $this->message([['type' => 'text', 'text' => "Hoy marcaron **2 personas**.\n\n| Nombre | Entrada |\n|---|---|\n| Elena Empleada | 08:00 |"]], 'end_turn'),
        ]);

        $response = $this->actingAs($admin)->postJson(route('ai.ask'), ['question' => '¿Quién vino hoy?'])->assertOk();

        $this->assertStringContainsString('<strong>2 personas</strong>', $response->json('message.html'));
        $this->assertStringContainsString('<table>', $response->json('message.html'));
        $this->assertSame(['team_status_now'], $response->json('message.tools'));

        // Primera petición: modelo, herramientas, esfuerzo y reintento por política del servidor.
        $first = $this->requestBody(0);
        $this->assertSame('claude-opus-5-5', $first['model']);
        $this->assertSame('medium', $first['output_config']['effort']);
        $this->assertSame('default', $first['fallbacks']);
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $this->history[0]['request']->getHeaderLine('anthropic-beta'));
        $this->assertSame('sk-ant-test', $this->history[0]['request']->getHeaderLine('x-api-key'));
        $this->assertContains('attendance_summary', array_column($first['tools'], 'name'));
        $this->assertStringContainsString('"name":"team_status_now"', (string) $this->history[0]['request']->getBody());
        $this->assertMatchesRegularExpression('/"name":"team_status_now".*?"properties":\{\}/s', (string) $this->history[0]['request']->getBody());
        $this->assertStringContainsString('Primer toque', str_replace('primer toque', 'Primer toque', $first['system'][0]['text']));

        // Segunda petición: se reenvía el turno del asistente sin cambios y el resultado de la herramienta.
        $second = $this->requestBody(1);
        $assistantTurn = $second['messages'][1];
        $this->assertSame('assistant', $assistantTurn['role']);
        $this->assertSame('sig-1', $assistantTurn['content'][0]['signature']);
        $toolResult = $second['messages'][2]['content'][0];
        $this->assertSame('tool_result', $toolResult['type']);
        $this->assertSame('toolu_1', $toolResult['tool_use_id']);
        $this->assertStringContainsString('Elena Empleada', $toolResult['content']);
        $this->assertStringContainsString('Otro Trabajador', $toolResult['content']);

        $conversation = AiConversation::sole();
        $this->assertSame('¿Quién vino hoy?', $conversation->title);
        $this->assertSame(['user', 'assistant'], $conversation->messages()->pluck('role')->all());
    }

    public function test_employee_tools_are_limited_to_their_own_data(): void
    {
        [$employee] = $this->seedAttendance();

        $this->fakeClaude([
            $this->message([
                ['type' => 'tool_use', 'id' => 'toolu_a', 'name' => 'attendance_records', 'input' => ['date_from' => today()->toDateString(), 'employee' => 'Otro']],
                ['type' => 'tool_use', 'id' => 'toolu_b', 'name' => 'list_employees', 'input' => new \stdClass],
            ], 'tool_use'),
            $this->message([['type' => 'text', 'text' => 'Solo puedo ver tus datos.']], 'end_turn'),
        ]);

        $this->actingAs($employee)->postJson(route('ai.ask'), ['question' => '¿A qué hora llegó Otro?'])->assertOk();

        $results = $this->requestBody(1)['messages'][2]['content'];
        $this->assertCount(2, $results, 'Todos los resultados van en un solo mensaje.');
        foreach ($results as $result) {
            $this->assertStringContainsString('Elena Empleada', $result['content']);
            $this->assertStringNotContainsString('Otro Trabajador', $result['content']);
        }
        $this->assertStringContainsString('SUS PROPIOS datos', $this->requestBody(0)['system'][0]['text']);
    }

    public function test_follow_up_questions_send_previous_turns_as_text(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = $admin->aiConversations()->create(['title' => 'Prueba']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Hola']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => '¡Hola! ¿En qué te ayudo?']);

        $this->fakeClaude([$this->message([['type' => 'text', 'text' => 'Claro.']], 'end_turn')]);

        $this->actingAs($admin)->postJson(route('ai.ask'), ['question' => 'Gracias', 'conversation_id' => $conversation->id])->assertOk();

        $messages = $this->requestBody(0)['messages'];
        $this->assertSame(['user', 'assistant', 'user'], array_column($messages, 'role'));
        $this->assertSame('Gracias', $messages[2]['content']);
    }

    public function test_missing_api_key_is_reported(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->postJson(route('ai.ask'), ['question' => 'Hola'])->assertOk()->json('message.html');
        $this->assertStringContainsString('clave de API', $html);

    }

    public function test_refusals_are_handled(): void
    {
        $admin = User::factory()->admin()->create();

        $this->fakeClaude([$this->message([], 'refusal')]);
        $html = $this->actingAs($admin)->postJson(route('ai.ask'), ['question' => 'Algo prohibido'])->assertOk()->json('message.html');

        $this->assertStringContainsString('no puedo ayudar', $html);
    }

    public function test_users_cannot_use_other_peoples_conversations(): void
    {
        $owner = User::factory()->admin()->create();
        $intruder = User::factory()->admin()->create();
        $conversation = $owner->aiConversations()->create();

        $this->actingAs($intruder)->get(route('ai.index', $conversation))->assertNotFound();
        $this->actingAs($intruder)->postJson(route('ai.ask'), ['question' => 'x', 'conversation_id' => $conversation->id])->assertNotFound();
        $this->actingAs($intruder)->delete(route('ai.destroy', $conversation))->assertNotFound();
    }
}
