<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintUpdate;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ComplaintAssignedNotification;
use App\Notifications\ComplaintCreatedNotification;
use App\Notifications\ComplaintUpdatedNotification;
use App\Services\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $manager = $user->can('complaints.manage');

        $filters = [
            'status' => $request->string('estado')->toString() ?: null,
            'priority' => $request->string('prioridad')->toString() ?: null,
            'category' => $request->string('categoria')->toString() ?: null,
            'mine' => $request->boolean('asignadas'),
            'q' => trim((string) $request->query('q')),
        ];

        $complaints = Complaint::query()
            ->with(['location', 'assignee', 'user'])
            ->when(! $manager, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('assigned_to', $user->id)))
            ->when($filters['mine'], fn (Builder $q) => $q->where('assigned_to', $user->id))
            ->when($filters['status'], fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['priority'], fn (Builder $q, $v) => $q->where('priority', $v))
            ->when($filters['category'], fn (Builder $q, $v) => $q->where('category', $v))
            ->when($filters['q'], fn (Builder $q, $term) => $q->where(fn (Builder $q) => $q
                ->where('subject', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")))
            ->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 when 'resolved' then 2 else 3 end")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = Complaint::query()
            ->when(! $manager, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('assigned_to', $user->id)))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('complaints.index', compact('complaints', 'filters', 'counts', 'manager'));
    }

    public function create(Request $request): View
    {
        return view('complaints.create', [
            'locations' => Location::query()->active()->orderBy('name')->pluck('name', 'id'),
            'selectedLocation' => $request->integer('ubicacion') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys(Complaint::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Complaint::PRIORITIES))],
            'location_id' => ['nullable', Rule::exists('locations', 'id')],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [], [
            'subject' => 'asunto',
            'description' => 'descripción',
            'category' => 'categoría',
            'priority' => 'prioridad',
            'location_id' => 'ubicación',
            'attachment' => 'adjunto',
        ]);

        $complaint = Complaint::create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'location_id' => $data['location_id'] ?? null,
            'attachment_path' => $request->file('attachment')?->store('complaints', 'local'),
            'status' => 'open',
        ]);

        if (Setting::bool('notify_complaints')) {
            $this->notifier->toPermission('complaints.manage', new ComplaintCreatedNotification($complaint), except: $request->user());
        }

        return redirect()->route('complaints.show', $complaint)->with('success', "Queja {$complaint->code} registrada. Te avisaremos cuando haya novedades.");
    }

    public function show(Request $request, Complaint $complaint): View
    {
        $user = $request->user();
        abort_unless($complaint->canBeViewedBy($user), 403);

        $manager = $user->can('complaints.manage');

        $complaint->load(['location', 'assignee', 'user', 'updates.user']);

        return view('complaints.show', [
            'complaint' => $complaint,
            'manager' => $manager,
            'updates' => $complaint->updates->filter(fn (ComplaintUpdate $u) => $manager || ! $u->is_internal),
            'staff' => $manager ? User::query()->active()->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function reply(Request $request, Complaint $complaint): RedirectResponse
    {
        $user = $request->user();
        abort_unless($complaint->canBeViewedBy($user), 403);

        $manager = $user->can('complaints.manage');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ], [], ['body' => 'mensaje']);

        $update = $complaint->updates()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => $manager && ($data['is_internal'] ?? false),
        ]);

        $complaint->touch();

        if (! $update->is_internal) {
            $this->notifyParticipants($complaint, $update, $user);
        }

        return back()->with('success', $update->is_internal ? 'Nota interna agregada.' : 'Respuesta enviada.');
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Complaint::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(Complaint::PRIORITIES))],
            'category' => ['required', Rule::in(array_keys(Complaint::CATEGORIES))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $previousStatus = $complaint->status;
        $previousAssignee = $complaint->assigned_to;

        $complaint->fill([
            'status' => $data['status'],
            'priority' => $data['priority'],
            'category' => $data['category'],
            'assigned_to' => $data['assigned_to'] ?? null,
        ]);

        if (in_array($data['status'], ['resolved', 'closed'], true) && ! $complaint->resolved_at) {
            $complaint->resolved_at = now();
        } elseif (in_array($data['status'], ['open', 'in_progress'], true)) {
            $complaint->resolved_at = null;
        }

        $complaint->save();

        $statusChanged = $previousStatus !== $complaint->status;

        if ($statusChanged || filled($data['note'] ?? null)) {
            $update = $complaint->updates()->create([
                'user_id' => $request->user()->id,
                'body' => $data['note'] ?? null,
                'status_from' => $statusChanged ? $previousStatus : null,
                'status_to' => $statusChanged ? $complaint->status : null,
            ]);

            $this->notifyReporter($complaint, $update, $request->user());
        }

        if ($complaint->assigned_to && $complaint->assigned_to !== $previousAssignee && $complaint->assigned_to !== $request->user()->id) {
            $this->notifier->send($complaint->assignee, new ComplaintAssignedNotification($complaint));
        }

        return back()->with('success', 'Queja actualizada.');
    }

    public function destroy(Complaint $complaint): RedirectResponse
    {
        if ($complaint->attachment_path) {
            Storage::disk('local')->delete($complaint->attachment_path);
        }

        $complaint->delete();

        return redirect()->route('complaints.index')->with('success', 'Queja eliminada.');
    }

    public function attachment(Request $request, Complaint $complaint): StreamedResponse
    {
        abort_unless($complaint->canBeViewedBy($request->user()), 403);
        abort_unless($complaint->attachment_path && Storage::disk('local')->exists($complaint->attachment_path), 404);

        return Storage::disk('local')->response($complaint->attachment_path);
    }

    private function notifyParticipants(Complaint $complaint, ComplaintUpdate $update, User $author): void
    {
        $isReporter = $complaint->user_id === $author->id;

        if ($isReporter) {
            // El reportante respondió: avisar al responsable o a los gestores.
            if ($complaint->assignee) {
                $this->notifier->send($complaint->assignee, new ComplaintUpdatedNotification($complaint, $update, forStaff: true));
            } else {
                $this->notifier->toPermission('complaints.manage', new ComplaintUpdatedNotification($complaint, $update, forStaff: true), except: $author);
            }

            return;
        }

        $this->notifyReporter($complaint, $update, $author);
    }

    private function notifyReporter(Complaint $complaint, ComplaintUpdate $update, User $author): void
    {
        if ($complaint->user_id === $author->id) {
            return;
        }

        $notification = new ComplaintUpdatedNotification($complaint, $update);

        if ($complaint->user) {
            $this->notifier->send($complaint->user, $notification);
        } else {
            $this->notifier->toEmail($complaint->reporter_email, $notification);
        }
    }
}
