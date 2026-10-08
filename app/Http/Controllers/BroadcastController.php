<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Notifications\BroadcastNotification;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BroadcastController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $broadcasts = Broadcast::query()
            ->visibleTo($user)
            ->with('sender')
            ->withExists(['readers as read' => fn ($q) => $q->whereKey($user->id)])
            ->latest()
            ->paginate(15);

        return view('broadcasts.index', compact('broadcasts'));
    }

    public function show(Request $request, Broadcast $broadcast): View
    {
        $user = $request->user();

        abort_unless(
            Broadcast::query()->visibleTo($user)->whereKey($broadcast->id)->exists(),
            403
        );

        $broadcast->readers()->syncWithoutDetaching([$user->id => ['read_at' => now()]]);

        $stats = null;
        if ($user->can('broadcasts.send')) {
            $stats = [
                'recipients' => $broadcast->recipientsQuery()->count(),
                'read' => $broadcast->readers()->count(),
            ];
        }

        return view('broadcasts.show', ['broadcast' => $broadcast->load('sender'), 'stats' => $stats]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $user = $request->user();

        $ids = Broadcast::query()->unreadBy($user)->pluck('id');
        $user->readBroadcasts()->syncWithoutDetaching($ids->mapWithKeys(fn ($id) => [$id => ['read_at' => now()]])->all());

        return back()->with('success', 'Todas las difusiones quedaron marcadas como leídas.');
    }

    public function create(): View
    {
        return view('broadcasts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(array_keys(Broadcast::AUDIENCES))],
            'priority' => ['required', Rule::in(array_keys(Broadcast::PRIORITIES))],
            'send_email' => ['nullable', 'boolean'],
        ], [], [
            'title' => 'título',
            'body' => 'mensaje',
            'audience' => 'destinatarios',
            'priority' => 'prioridad',
        ]);

        $broadcast = Broadcast::create($data + [
            'user_id' => $request->user()->id,
            'send_email' => $request->boolean('send_email'),
        ]);

        $broadcast->readers()->attach($request->user()->id, ['read_at' => now()]);

        if ($broadcast->send_email) {
            $this->notifier->send(
                $broadcast->recipientsQuery()->whereKeyNot($request->user()->id)->get(),
                new BroadcastNotification($broadcast)
            );
        }

        return redirect()->route('broadcasts.show', $broadcast)->with('success', 'Difusión enviada'.($broadcast->send_email ? ' (también por correo).' : '.'));
    }

    public function destroy(Broadcast $broadcast): RedirectResponse
    {
        $broadcast->delete();

        return redirect()->route('broadcasts.index')->with('success', 'Difusión eliminada.');
    }
}
