<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        return view('chat.index', [
            'contacts' => $this->contactList($request->user()),
            'initial' => $request->query('con'),
        ]);
    }

    public function contacts(Request $request): JsonResponse
    {
        return response()->json(['contacts' => $this->contactList($request->user())]);
    }

    /**
     * Mensajes de un canal: "general" o el id de otro usuario. Admite ?after={id} para sondeo incremental.
     */
    public function messages(Request $request): JsonResponse
    {
        $user = $request->user();
        $channel = (string) $request->query('canal', 'general');
        $after = $request->integer('after');

        $query = $this->channelQuery($user, $channel)->with('sender');

        $messages = $after > 0
            ? $query->where('id', '>', $after)->orderBy('id')->limit(200)->get()
            : $query->orderByDesc('id')->limit(60)->get()->reverse()->values();

        $this->markRead($user, $channel);

        return response()->json([
            'messages' => $messages->map(fn (Message $m) => $m->toChatArray($user))->values(),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'canal' => ['required', 'string', 'max:20'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $recipientId = null;
        if ($data['canal'] !== 'general') {
            $recipient = User::query()->active()->whereKey((int) $data['canal'])->first();
            abort_unless($recipient && $recipient->hasPermission('chat.use') && ! $recipient->is($user), 422, 'Destinatario no válido.');
            $recipientId = $recipient->id;
        }

        $message = Message::create([
            'sender_id' => $user->id,
            'recipient_id' => $recipientId,
            'body' => trim($data['body']),
        ])->load('sender');

        return response()->json(['message' => $message->toChatArray($user)], 201);
    }

    /**
     * @return Builder<Message>
     */
    private function channelQuery(User $user, string $channel): Builder
    {
        if ($channel === 'general') {
            return Message::query()->whereNull('recipient_id');
        }

        $otherId = (int) $channel;

        return Message::query()->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('sender_id', $user->id)->where('recipient_id', $otherId))
            ->orWhere(fn (Builder $q) => $q->where('sender_id', $otherId)->where('recipient_id', $user->id)));
    }

    private function markRead(User $user, string $channel): void
    {
        if ($channel === 'general') {
            $user->forceFill(['general_chat_read_at' => now()])->saveQuietly();

            return;
        }

        Message::query()
            ->where('sender_id', (int) $channel)
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contactList(User $user): array
    {
        $unread = Message::query()
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->select('sender_id', DB::raw('count(*) as total'))
            ->groupBy('sender_id')
            ->pluck('total', 'sender_id');

        $lastMessages = Message::query()
            ->whereNotNull('recipient_id')
            ->where(fn (Builder $q) => $q->where('sender_id', $user->id)->orWhere('recipient_id', $user->id))
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->unique(fn (Message $m) => $m->sender_id === $user->id ? $m->recipient_id : $m->sender_id)
            ->keyBy(fn (Message $m) => $m->sender_id === $user->id ? $m->recipient_id : $m->sender_id);

        $generalLast = Message::query()->whereNull('recipient_id')->latest('id')->first();
        $generalUnread = Message::query()
            ->whereNull('recipient_id')
            ->where('sender_id', '!=', $user->id)
            ->when($user->general_chat_read_at, fn (Builder $q) => $q->where('created_at', '>', $user->general_chat_read_at))
            ->count();

        $contacts = [[
            'id' => 'general',
            'name' => 'Canal general',
            'subtitle' => 'Todo el equipo',
            'initials' => '#',
            'online' => false,
            'unread' => $generalUnread,
            'last' => $generalLast ? str($generalLast->body)->limit(40)->toString() : null,
            'last_at' => $generalLast?->created_at?->timestamp ?? PHP_INT_MAX,
        ]];

        $users = User::query()->active()->whereKeyNot($user->id)->orderBy('name')->get()
            ->filter(fn (User $u) => $u->hasPermission('chat.use'));

        foreach ($users as $other) {
            $last = $lastMessages->get($other->id);

            $contacts[] = [
                'id' => (string) $other->id,
                'name' => $other->name,
                'subtitle' => $other->position ?: $other->roleLabel(),
                'initials' => $other->initials(),
                'online' => $other->isOnline(),
                'unread' => (int) ($unread[$other->id] ?? 0),
                'last' => $last ? str($last->body)->limit(40)->toString() : null,
                'last_at' => $last?->created_at?->timestamp ?? 0,
            ];
        }

        // Canal general primero; luego conversaciones recientes y el resto alfabético.
        $general = array_shift($contacts);
        usort($contacts, fn ($a, $b) => [$b['last_at'], $a['name']] <=> [$a['last_at'], $b['name']]);

        return [$general, ...$contacts];
    }
}
