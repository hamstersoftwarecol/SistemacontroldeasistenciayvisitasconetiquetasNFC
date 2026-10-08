<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Services\AttendanceService;
use App\Services\TeamStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TeamStatusService $team,
        private readonly AttendanceService $attendance,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $today = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $this->attendance->workDateFor($user, CarbonImmutable::now()))
            ->with('scans.location')
            ->first();

        $data = [
            'user' => $user,
            'today' => $today,
            'myComplaints' => Complaint::query()->where('user_id', $user->id)->open()->count(),
        ];

        if ($user->can('team.view')) {
            $board = $this->team->board();

            $data += [
                'summary' => $board['summary'],
                'feed' => $this->team->feed(),
                'chart' => $this->lastDays(7),
                'recentComplaints' => Complaint::query()->with('location')->open()->latest()->limit(5)->get(),
                'lateToday' => collect($board['rows'])->where('is_late', true)->values(),
            ];
        }

        return view('dashboard', $data);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('team.view'), 403);

        return response()->json([
            'summary' => $this->team->board()['summary'],
            'feed' => $this->team->feed(),
        ]);
    }

    /**
     * Presentes y tardanzas de los últimos días para el gráfico del panel.
     *
     * @return list<array{label: string, date: string, present: int, late: int, working: bool}>
     */
    private function lastDays(int $days): array
    {
        $from = today()->subDays($days - 1);

        $rows = Attendance::query()
            ->whereDate('date', '>=', $from)
            ->get(['date', 'is_late'])
            ->groupBy(fn (Attendance $a) => $a->date->toDateString());

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $group = $rows->get($day->toDateString(), collect());

            $result[] = [
                'label' => ucfirst($day->translatedFormat('D')),
                'date' => $day->translatedFormat('d M'),
                'present' => $group->count(),
                'late' => $group->where('is_late', true)->count(),
                'working' => $this->attendance->isWorkingDay($day),
            ];
        }

        return $result;
    }
}
