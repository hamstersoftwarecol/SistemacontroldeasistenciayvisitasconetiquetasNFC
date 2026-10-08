<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AttendanceAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    public function __construct(private readonly AttendanceAssistant $assistant) {}

    public function index(Request $request, ?AiConversation $conversation = null): View
    {
        $user = $request->user();

        if ($conversation) {
            abort_unless($conversation->user_id === $user->id, 404);
            $conversation->load('messages');
        }

        $suggestions = $user->can('ai.all_data')
            ? [
                '¿Quién llegó tarde hoy?',
                '¿Quiénes no han marcado entrada hoy?',
                'Resumen de horas trabajadas por empleado esta semana',
                '¿Qué ubicaciones no se visitaron ayer?',
                'Top 5 de puntualidad este mes',
                '¿Cuántas quejas abiertas hay y de qué tipo?',
            ]
            : [
                '¿A qué hora marqué entrada hoy?',
                '¿Cuántas horas trabajé esta semana?',
                '¿Cuántas veces llegué tarde este mes?',
                '¿Qué ubicaciones visité ayer?',
            ];

        return view('ai.index', [
            'conversation' => $conversation,
            'conversations' => $user->aiConversations()->latest('updated_at')->limit(30)->get(),
            'configured' => $this->assistant->isConfigured(),
            'suggestions' => $suggestions,
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        $conversation = isset($data['conversation_id'])
            ? $user->aiConversations()->findOrFail($data['conversation_id'])
            : $user->aiConversations()->create(['title' => 'Nueva conversación']);

        @set_time_limit(180);

        $answer = $this->assistant->ask($user, $conversation, trim($data['question']));

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->fresh()->title,
                'url' => route('ai.index', $conversation),
            ],
            'message' => $this->present($answer),
        ]);
    }

    public function destroy(Request $request, AiConversation $conversation): RedirectResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $conversation->delete();

        return redirect()->route('ai.index')->with('success', 'Conversación eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(AiMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'html' => $message->role === 'assistant' ? $message->html() : null,
            'text' => $message->role === 'user' ? $message->content : null,
            'tools' => $message->tools_used ?? [],
            'time' => $message->created_at->format('H:i'),
        ];
    }
}
