<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Location;
use App\Models\Scan;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $attendances = Attendance::query()
            ->with(['user', 'checkInLocation', 'checkOutLocation'])
            ->whereDate('date', '>=', $filters['from'])
            ->whereDate('date', '<=', $filters['to'])
            ->when($filters['user_id'], fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filters['department'], fn (Builder $q, $dep) => $q->whereHas('user', fn (Builder $u) => $u->where('department', $dep)))
            ->when($filters['status'] === 'late', fn (Builder $q) => $q->where('is_late', true))
            ->when($filters['status'] === 'on_time', fn (Builder $q) => $q->where('is_late', false))
            ->when($filters['status'] === 'open', fn (Builder $q) => $q->whereNull('check_out_at'))
            ->orderByDesc('date')
            ->orderBy('check_in_at')
            ->paginate(30)
            ->withQueryString();

        return view('attendances.index', [
            'attendances' => $attendances,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'departments' => User::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department', 'department'),
        ]);
    }

    public function show(Attendance $attendance): View
    {
        $attendance->load(['user', 'scans.location', 'checkInLocation', 'checkOutLocation']);

        return view('attendances.show', compact('attendance'));
    }

    public function create(): View
    {
        return view('attendances.form', [
            'attendance' => new Attendance(['date' => today()]),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id'),
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (Attendance::query()->where('user_id', $data['user_id'])->whereDate('date', $data['date'])->exists()) {
            throw ValidationException::withMessages(['date' => 'Ese empleado ya tiene un registro para esta fecha. Edítalo en lugar de crear otro.']);
        }

        $attendance = Attendance::create($this->payload($data) + ['user_id' => $data['user_id'], 'date' => $data['date']]);
        $this->syncManualScans($attendance, $data['location_id']);
        $this->attendance->refresh($attendance);

        return redirect()->route('attendances.show', $attendance)->with('success', 'Registro manual creado.');
    }

    public function edit(Attendance $attendance): View
    {
        return view('attendances.form', [
            'attendance' => $attendance->load('user'),
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        $data = $this->validated($request, $attendance);

        $attendance->fill($this->payload($data, $attendance->date->toDateString()));
        $attendance->save();
        $this->attendance->refresh($attendance);

        return redirect()->route('attendances.show', $attendance)->with('success', 'Registro actualizado.');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        $attendance->delete();

        return redirect()->route('attendances.index')->with('success', 'Registro de asistencia eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Attendance $attendance = null): array
    {
        return $request->validate([
            'user_id' => [$attendance ? 'nullable' : 'required', Rule::exists('users', 'id')],
            'date' => [$attendance ? 'nullable' : 'required', 'date', 'before_or_equal:today'],
            'check_in' => ['required', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'location_id' => [$attendance ? 'nullable' : 'required', Rule::exists('locations', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'user_id' => 'empleado',
            'date' => 'fecha',
            'check_in' => 'hora de entrada',
            'check_out' => 'hora de salida',
            'location_id' => 'ubicación',
            'notes' => 'notas',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?string $date = null): array
    {
        $date ??= $data['date'];
        $checkIn = CarbonImmutable::parse("{$date} {$data['check_in']}");
        $checkOut = filled($data['check_out'] ?? null) ? CarbonImmutable::parse("{$date} {$data['check_out']}") : null;

        if ($checkOut && $checkOut->lessThan($checkIn)) {
            $checkOut = $checkOut->addDay(); // turno que termina después de medianoche
        }

        $payload = [
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'notes' => $data['notes'] ?? null,
        ];

        if (! empty($data['location_id'])) {
            $payload['check_in_location_id'] = $data['location_id'];
            $payload['check_out_location_id'] = $checkOut ? $data['location_id'] : null;
        }

        return $payload;
    }

    private function syncManualScans(Attendance $attendance, int $locationId): void
    {
        $attendance->scans()->create([
            'user_id' => $attendance->user_id,
            'location_id' => $locationId,
            'scanned_at' => $attendance->check_in_at,
            'type' => Scan::TYPE_CHECK_IN,
            'source' => 'manual',
            'comment' => 'Registro manual',
        ]);

        if ($attendance->check_out_at) {
            $attendance->scans()->create([
                'user_id' => $attendance->user_id,
                'location_id' => $locationId,
                'scanned_at' => $attendance->check_out_at,
                'type' => Scan::TYPE_CHECK_OUT,
                'source' => 'manual',
                'comment' => 'Registro manual',
            ]);
        }

        $attendance->scans_count = $attendance->scans()->count();
    }

    /**
     * @return array{from: string, to: string, user_id: int|null, department: string|null, status: string|null}
     */
    private function filters(Request $request): array
    {
        $from = $request->date('desde')?->toDateString() ?? today()->startOfMonth()->toDateString();
        $to = $request->date('hasta')?->toDateString() ?? today()->toDateString();

        return [
            'from' => $from,
            'to' => $to < $from ? $from : $to,
            'user_id' => $request->integer('empleado') ?: null,
            'department' => $request->string('departamento')->toString() ?: null,
            'status' => $request->string('estado')->toString() ?: null,
        ];
    }
}
