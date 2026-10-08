<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Scan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Datos de los informes de asistencia, visitas y quejas (pantalla, impresión y exportación).
 */
class ReportService
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * @param  array{from: string, to: string, user_id?: int|null, department?: string|null}  $f
     * @return array{rows: Collection<int, Attendance>, employees: Collection<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function attendance(array $f): array
    {
        $users = User::query()
            ->when($f['user_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
            ->when($f['department'] ?? null, fn (Builder $q, $dep) => $q->where('department', $dep))
            ->orderBy('name')
            ->get();

        $rows = Attendance::query()
            ->with(['user', 'checkInLocation', 'checkOutLocation'])
            ->whereIn('user_id', $users->modelKeys())
            ->whereDate('date', '>=', $f['from'])
            ->whereDate('date', '<=', $f['to'])
            ->orderBy('date')
            ->orderBy('check_in_at')
            ->get();

        $workingDays = $this->workingDays($f['from'], $f['to']);
        $byUser = $rows->groupBy('user_id');

        $employees = $users
            ->map(function (User $user) use ($byUser, $workingDays) {
                /** @var Collection<int, Attendance> $records */
                $records = $byUser->get($user->id, collect());
                $userStart = CarbonImmutable::instance($user->created_at)->startOfDay()->toDateString();
                $expected = $workingDays->filter(fn (string $day) => $day >= $userStart);
                $presentDays = $records->map(fn (Attendance $a) => $a->date->toDateString());
                $absences = $user->is_active ? $expected->diff($presentDays)->count() : 0;

                $avgCheckIn = $records->isNotEmpty()
                    ? (int) round($records->avg(fn (Attendance $a) => $a->check_in_at->hour * 60 + $a->check_in_at->minute))
                    : null;

                return [
                    'user' => $user,
                    'name' => $user->name,
                    'department' => $user->department,
                    'days' => $records->count(),
                    'late_days' => $records->where('is_late', true)->count(),
                    'late_minutes' => (int) $records->sum('late_minutes'),
                    'worked_minutes' => (int) $records->sum('worked_minutes'),
                    'absences' => $absences,
                    'avg_check_in' => $avgCheckIn !== null ? sprintf('%02d:%02d', intdiv($avgCheckIn, 60), $avgCheckIn % 60) : null,
                    'punctuality' => $records->count() ? (int) round(($records->count() - $records->where('is_late', true)->count()) / $records->count() * 100) : null,
                ];
            })
            ->filter(fn (array $row) => $row['user']->is_active || $row['days'] > 0)
            ->values();

        return [
            'rows' => $rows,
            'employees' => $employees,
            'totals' => [
                'records' => $rows->count(),
                'late' => $rows->where('is_late', true)->count(),
                'worked_minutes' => (int) $rows->sum('worked_minutes'),
                'absences' => (int) $employees->sum('absences'),
                'working_days' => $workingDays->count(),
                'employees' => $employees->count(),
            ],
        ];
    }

    /**
     * @param  array{from: string, to: string, user_id?: int|null, location_id?: int|null}  $f
     * @return array{rows: Collection<int, Scan>, locations: Collection<int, array<string, mixed>>, employees: Collection<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function visits(array $f): array
    {
        $rows = Scan::query()
            ->with(['user', 'location'])
            ->whereDate('scanned_at', '>=', $f['from'])
            ->whereDate('scanned_at', '<=', $f['to'])
            ->when($f['user_id'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($f['location_id'] ?? null, fn (Builder $q, $id) => $q->where('location_id', $id))
            ->orderBy('scanned_at')
            ->get();

        $locations = $rows->groupBy('location_id')->map(fn (Collection $scans) => [
            'name' => $scans->first()->location->name,
            'area' => $scans->first()->location->area,
            'visits' => $scans->count(),
            'employees' => $scans->pluck('user_id')->unique()->count(),
            'comments' => $scans->whereNotNull('comment')->count(),
            'last' => $scans->max('scanned_at'),
        ])->sortByDesc('visits')->values();

        $employees = $rows->groupBy('user_id')->map(fn (Collection $scans) => [
            'name' => $scans->first()->user->name,
            'department' => $scans->first()->user->department,
            'visits' => $scans->count(),
            'locations' => $scans->pluck('location_id')->unique()->count(),
            'days' => $scans->map(fn (Scan $s) => $s->scanned_at->toDateString())->unique()->count(),
        ])->sortByDesc('visits')->values();

        return [
            'rows' => $rows,
            'locations' => $locations,
            'employees' => $employees,
            'totals' => [
                'visits' => $rows->count(),
                'locations' => $locations->count(),
                'employees' => $employees->count(),
                'comments' => $rows->whereNotNull('comment')->count(),
            ],
        ];
    }

    /**
     * @param  array{from: string, to: string, status?: string|null, category?: string|null, priority?: string|null}  $f
     * @return array{rows: Collection<int, Complaint>, by_status: array<string, int>, by_category: array<string, int>, by_priority: array<string, int>, totals: array<string, int|float|null>}
     */
    public function complaints(array $f): array
    {
        $rows = Complaint::query()
            ->with(['location', 'user', 'assignee'])
            ->whereDate('created_at', '>=', $f['from'])
            ->whereDate('created_at', '<=', $f['to'])
            ->when($f['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($f['category'] ?? null, fn (Builder $q, $v) => $q->where('category', $v))
            ->when($f['priority'] ?? null, fn (Builder $q, $v) => $q->where('priority', $v))
            ->orderBy('created_at')
            ->get();

        $count = fn (array $labels, string $field) => collect($labels)
            ->mapWithKeys(fn (string $label, string $key) => [$label => $rows->where($field, $key)->count()])
            ->all();

        $resolved = $rows->whereNotNull('resolved_at');
        $avgHours = $resolved->isNotEmpty()
            ? round($resolved->avg(fn (Complaint $c) => $c->created_at->diffInMinutes($c->resolved_at)) / 60, 1)
            : null;

        return [
            'rows' => $rows,
            'by_status' => $count(Complaint::STATUSES, 'status'),
            'by_category' => array_filter($count(Complaint::CATEGORIES, 'category')),
            'by_priority' => $count(Complaint::PRIORITIES, 'priority'),
            'totals' => [
                'total' => $rows->count(),
                'open' => $rows->whereIn('status', ['open', 'in_progress'])->count(),
                'resolved' => $resolved->count(),
                'avg_hours' => $avgHours,
            ],
        ];
    }

    /**
     * Días laborables del rango (hasta hoy como máximo).
     *
     * @return Collection<int, string>
     */
    public function workingDays(string $from, string $to): Collection
    {
        $days = collect();
        $end = min(CarbonImmutable::parse($to), CarbonImmutable::today());

        for ($day = CarbonImmutable::parse($from); $day->lte($end); $day = $day->addDay()) {
            if ($this->attendance->isWorkingDay($day)) {
                $days->push($day->toDateString());
            }
        }

        return $days;
    }
}
