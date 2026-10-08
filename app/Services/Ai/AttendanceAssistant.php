<?php

namespace App\Services\Ai;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Asistente de IA conectado a la base de datos de asistencia mediante herramientas de solo lectura.
 */
class AttendanceAssistant
{
    private const MAX_STEPS = 8;

    private const HISTORY_MESSAGES = 20;

    public function __construct(
        private readonly ClaudeGateway $gateway,
        private readonly AssistantTools $tools,
        private readonly AttendanceService $attendance,
    ) {}

    public function isConfigured(): bool
    {
        return $this->gateway->isConfigured();
    }

    /**
     * Responde una pregunta dentro de una conversación y guarda ambos mensajes.
     */
    public function ask(User $user, AiConversation $conversation, string $question): AiMessage
    {
        $history = $conversation->messages()
            ->reorder('id', 'desc')
            ->limit(self::HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        $conversation->messages()->create(['role' => 'user', 'content' => $question]);

        if (blank($conversation->title) || $conversation->title === 'Nueva conversación') {
            $conversation->update(['title' => Str::limit($question, 60)]);
        }

        // Solo se reenvía el texto de turnos anteriores (sin bloques de razonamiento).
        $messages = [];
        foreach ($history as $message) {
            $messages[] = ['role' => $message->role === 'assistant' ? 'assistant' : 'user', 'content' => $message->content];
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        [$answer, $toolsUsed] = $this->runLoop($user, $messages);

        $conversation->touch();

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $answer,
            'tools_used' => $toolsUsed ?: null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array{0: string, 1: list<string>}
     */
    private function runLoop(User $user, array $messages): array
    {
        $system = [['type' => 'text', 'text' => $this->systemPrompt($user)]];
        $definitions = $this->tools->definitions();
        $toolsUsed = [];

        try {
            for ($step = 0; $step < self::MAX_STEPS; $step++) {
                $turn = $this->gateway->send($system, $definitions, $messages);

                if ($turn->stopReason === 'refusal') {
                    return ['Lo siento, no puedo ayudar con esa solicitud. Intenta reformular tu pregunta sobre asistencia, visitas o quejas.', $toolsUsed];
                }

                if ($turn->stopReason === 'tool_use' && $turn->toolUses !== []) {
                    $messages[] = ['role' => 'assistant', 'content' => $turn->rawContent];

                    $results = [];
                    foreach ($turn->toolUses as $call) {
                        $toolsUsed[] = $call['name'];
                        $result = $this->tools->run($user, $call['name'], $call['input']);
                        $results[] = [
                            'type' => 'tool_result',
                            'toolUseID' => $call['id'],
                            'content' => $result['content'],
                            'isError' => $result['is_error'],
                        ];
                    }

                    // Todos los resultados van juntos en un único mensaje del usuario.
                    $messages[] = ['role' => 'user', 'content' => $results];

                    continue;
                }

                if ($turn->stopReason === 'pause_turn') {
                    $messages[] = ['role' => 'assistant', 'content' => $turn->rawContent];

                    continue;
                }

                $text = trim($turn->text);

                if ($turn->stopReason === 'max_tokens') {
                    $text .= "\n\n_(La respuesta se cortó por su longitud. Pide un periodo más corto o un resumen.)_";
                }

                return [$text !== '' ? $text : 'No obtuve una respuesta. Intenta de nuevo.', array_values(array_unique($toolsUsed))];
            }

            return ['La consulta requirió demasiados pasos. Intenta hacer una pregunta más concreta.', array_values(array_unique($toolsUsed))];
        } catch (AuthenticationException|PermissionDeniedException) {
            return ['La clave de API de Claude no es válida o no tiene permisos. Revisa Ajustes → Asistente IA.', $toolsUsed];
        } catch (RateLimitException) {
            return ['El servicio de IA está recibiendo demasiadas solicitudes. Espera un momento e intenta de nuevo.', $toolsUsed];
        } catch (APIStatusException $e) {
            report($e);

            return ['El servicio de IA devolvió un error ('.($e->status ?? 'desconocido').'). Intenta de nuevo en unos minutos.', $toolsUsed];
        } catch (APIConnectionException) {
            return ['No se pudo conectar con el servicio de IA. Revisa la conexión a internet del servidor.', $toolsUsed];
        } catch (RuntimeException $e) {
            return [$e->getMessage(), $toolsUsed];
        } catch (Throwable $e) {
            report($e);

            return ['Ocurrió un error inesperado al consultar la IA.', $toolsUsed];
        }
    }

    private function systemPrompt(User $user): string
    {
        [$start, $end] = $this->attendance->shiftFor($user);
        $days = collect(Setting::workingDays())
            ->map(fn (int $d) => ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'][$d])
            ->implode(', ');

        $scope = $user->hasPermission('ai.all_data')
            ? 'Puede consultar los datos de todo el personal.'
            : 'Solo puede consultar SUS PROPIOS datos de asistencia y visitas; las herramientas ya filtran por su usuario. Si pide datos de otras personas, explícale que no tiene permiso.';

        $company = Setting::get('company_name');
        $today = now()->translatedFormat('l d \d\e F \d\e Y');
        $timezone = config('app.timezone');
        $grace = Setting::int('grace_minutes', 10);

        return <<<PROMPT
Eres el asistente de IA del sistema de control de asistencia NFC de «{$company}». Respondes en español, de forma clara y breve.

Contexto:
- Hoy es {$today} (zona horaria {$timezone}). Para fechas relativas («ayer», «esta semana», «el mes pasado») calcula las fechas exactas a partir de hoy; la semana empieza el lunes.
- Usuario: {$user->name} ({$user->roleLabel()}). {$scope}

Cómo funciona el sistema:
- El personal registra su asistencia acercando el teléfono a etiquetas NFC ubicadas en distintos lugares (habitaciones, oficinas, entradas).
- El primer toque del día es la ENTRADA y el último toque es la SALIDA; los toques intermedios son VISITAS a ubicaciones. Las horas trabajadas son el tiempo entre entrada y salida.
- Horario general: {$start} a {$end}, con {$grace} minutos de tolerancia. Días laborables: {$days}. Algunos empleados tienen horario propio.
- Una ausencia es un día laborable sin ningún toque.

Instrucciones:
- Usa las herramientas para obtener los datos; nunca inventes cifras, nombres ni horas. Si una herramienta no devuelve datos, dilo.
- Indica siempre el periodo que consultaste. Si la pregunta es ambigua en fechas, asume hoy y acláralo.
- Presenta listas o comparaciones en tablas Markdown cuando ayuden; para respuestas cortas usa una o dos frases.
- Puedes sugerir acciones (por ejemplo, revisar llegadas tarde recurrentes), pero no puedes modificar datos.
PROMPT;
    }
}
