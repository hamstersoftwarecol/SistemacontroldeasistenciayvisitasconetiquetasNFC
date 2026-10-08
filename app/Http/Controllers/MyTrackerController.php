<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Scan;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyTrackerController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $workDate = $this->attendance->workDateFor($user, CarbonImmutable::now());

        $today = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $workDate)
            ->with(['scans.location', 'checkInLocation', 'checkOutLocation'])
            ->first();

        $weekStart = today()->startOfWeek();
        $week = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $weekStart)
            ->get();

        [$shiftStart, $shiftEnd] = $this->attendance->shiftFor($user);

        return view('my.tracker', [
            'user' => $user,
            'today' => $today,
            'shiftStart' => $shiftStart,
            'shiftEnd' => $shiftEnd,
            'weekDays' => $week->count(),
            'weekLate' => $week->where('is_late', true)->count(),
            'weekMinutes' => (int) $week->sum('worked_minutes'),
            'recentScans' => Scan::query()
                ->where('user_id', $user->id)
                ->with('location')
                ->latest('scanned_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function history(Request $request): View
    {
        $user = $request->user();

        $month = $request->string('mes')->toString();
        $start = preg_match('/^\d{4}-\d{2}$/', $month)
            ? CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfDay()
            : CarbonImmutable::today()->startOfMonth();
        $end = $start->endOfMonth();

        $attendances = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->with(['scans.location', 'checkInLocation', 'checkOutLocation'])
            ->orderByDesc('date')
            ->get();

        $workingDays = 0;
        for ($day = $start; $day->lte(min($end, CarbonImmutable::today())); $day = $day->addDay()) {
            if ($this->attendance->isWorkingDay($day)) {
                $workingDays++;
            }
        }

        return view('my.history', [
            'attendances' => $attendances,
            'month' => $start,
            'previous' => $start->subMonth()->format('Y-m'),
            'next' => $start->addMonth()->lte(CarbonImmutable::today()) ? $start->addMonth()->format('Y-m') : null,
            'stats' => [
                'days' => $attendances->count(),
                'late' => $attendances->where('is_late', true)->count(),
                'minutes' => (int) $attendances->sum('worked_minutes'),
                'visits' => (int) $attendances->sum('scans_count'),
                'absences' => max(0, $workingDays - $attendances->filter(fn ($a) => $this->attendance->isWorkingDay($a->date))->count()),
            ],
        ]);
    }
}
