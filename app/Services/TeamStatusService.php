<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Scan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Estado del equipo en tiempo real (panel, equipo en vivo y asistente IA).
 */
class TeamStatusService
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int|bool|string>}
     */
    public function board(): array
    {
        $today = today();
        $window = max(5, Setting::int('active_window_minutes', 120));
        $workingDay = $this->attendance->isWorkingDay($today);

        $users = User::query()->active()->orderBy('name')->get();

        $attendances = Attendance::query()
            ->whereDate('date', $today)
            ->with(['checkInLocation', 'checkOutLocation'])
            ->get()
            ->keyBy('user_id');

        $lastScans = Scan::query()
            ->whereDate('scanned_at', $today)
            ->with('location')
            ->orderBy('scanned_at')
            ->get()
            ->keyBy('user_id'); // queda el último toque de cada usuario

        $rows = $users->map(function (User $user) use ($attendances, $lastScans, $window, $workingDay) {
            /** @var Attendance|null $attendance */
            $attendance = $attendances->get($user->id);
            /** @var Scan|null $last */
            $last = $lastScans->get($user->id);

            if (! $attendance) {
                $status = 'absent';
                $label = $workingDay ? 'Sin registrar' : 'Día libre';
            } elseif ($last && $last->scanned_at->gt(now()->subMinutes($window))) {
                $status = 'active';
                $label = 'En turno';
            } else {
                $status = 'idle';
                $label = 'Sin actividad reciente';
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'initials' => $user->initials(),
                'department' => $user->department,
                'position' => $user->position,
                'role' => $user->roleLabel(),
                'online' => $user->isOnline(),
                'status' => $status,
                'status_label' => $label,
                'check_in' => $attendance?->check_in_at?->format('H:i'),
                'check_out' => $attendance?->check_out_at?->format('H:i'),
                'is_late' => (bool) $attendance?->is_late,
                'late_minutes' => (int) $attendance?->late_minutes,
                'worked' => $attendance ? Attendance::formatMinutes($attendance->check_out_at ? $attendance->worked_minutes : (int) $attendance->check_in_at->diffInMinutes(now())) : null,
                'scans_count' => (int) $attendance?->scans_count,
                'last_location' => $last?->location?->name,
                'last_seen' => $last?->scanned_at?->format('H:i'),
                'last_seen_human' => $last?->scanned_at?->diffForHumans(),
            ];
        })->values();

        return [
            'rows' => $rows->all(),
            'summary' => $this->summary($rows, $workingDay),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int|bool|string>
     */
    private function summary(Collection $rows, bool $workingDay): array
    {
        return [
            'total' => $rows->count(),
            'present' => $rows->where('status', '!=', 'absent')->count(),
            'active' => $rows->where('status', 'active')->count(),
            'late' => $rows->where('is_late', true)->count(),
            'absent' => $workingDay ? $rows->where('status', 'absent')->count() : 0,
            'visits' => Scan::query()->whereDate('scanned_at', today())->count(),
            'open_complaints' => Complaint::query()->open()->count(),
            'working_day' => $workingDay,
            'updated_at' => now()->format('H:i:s'),
        ];
    }

    /**
     * Toques más recientes para el feed de actividad.
     *
     * @return list<array<string, mixed>>
     */
    public function feed(int $limit = 15): array
    {
        return Scan::query()
            ->with(['user', 'location'])
            ->latest('scanned_at')
            ->limit($limit)
            ->get()
            ->map(fn (Scan $scan) => [
                'id' => $scan->id,
                'user' => $scan->user->name,
                'initials' => $scan->user->initials(),
                'location' => $scan->location->name,
                'type' => $scan->type,
                'type_label' => $scan->typeLabel(),
                'time' => $scan->scanned_at->format('H:i'),
                'human' => $scan->scanned_at->diffForHumans(),
                'comment' => $scan->comment,
                'url' => route('scans.receipt', $scan),
            ])
            ->all();
    }
}
