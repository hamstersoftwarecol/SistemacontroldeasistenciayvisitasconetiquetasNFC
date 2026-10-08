<?php

namespace App\Services\Ai;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Location;
use App\Models\Scan;
use App\Models\User;
use App\Services\ReportService;
use App\Services\TeamStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Throwable;

/**
 * Herramientas de solo lectura que el asistente usa para consultar la base de datos.
 * Cada consulta respeta los permisos del usuario que pregunta.
 */
class AssistantTools
{
    private const MAX_ROWS = 200;

    public function __construct(
        private readonly TeamStatusService $team,
        private readonly ReportService $reports,
    ) {}

    /**
     * Definiciones para la API (claves en camelCase según el SDK de PHP).
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        $range = [
            'date_from' => ['type' => 'string', 'description' => 'Fecha inicial YYYY-MM-DD. Por defecto hoy.'],
            'date_to' => ['type' => 'string', 'description' => 'Fecha final YYYY-MM-DD (inclusive). Por defecto igual a date_from.'],
        ];
        $employee = ['employee' => ['type' => 'string', 'description' => 'Nombre (o parte del nombre) del empleado para filtrar. Opcional.']];

        return [
            [
                'name' => 'team_status_now',
                'description' => 'Estado del equipo en este momento: quién marcó entrada hoy, quién está en turno, quién llegó tarde, última ubicación y hora de cada persona, y totales del día (presentes, tarde, sin registrar, visitas, quejas abiertas).',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'attendance_records',
                'description' => 'Registros diarios de asistencia: fecha, empleado, hora de entrada (primer toque), hora de salida (último toque), horas trabajadas, minutos de retraso y número de toques. Úsalo para preguntas sobre días concretos o listados.',
                'inputSchema' => ['type' => 'object', 'properties' => $range + $employee],
            ],
            [
                'name' => 'attendance_summary',
                'description' => 'Resumen por empleado en un periodo: días trabajados, ausencias en días laborables, llegadas tarde, minutos de retraso, horas trabajadas, hora de entrada promedio y % de puntualidad. Ideal para rankings y totales.',
                'inputSchema' => ['type' => 'object', 'properties' => $range + $employee + [
                    'department' => ['type' => 'string', 'description' => 'Departamento para filtrar. Opcional.'],
                ]],
            ],
            [
                'name' => 'visits',
                'description' => 'Toques NFC individuales (visitas) con fecha, hora, empleado, ubicación, tipo (entrada/visita/salida) y comentario del empleado.',
                'inputSchema' => ['type' => 'object', 'properties' => $range + $employee + [
                    'location' => ['type' => 'string', 'description' => 'Nombre (o parte) de la ubicación. Opcional.'],
                    'only_with_comments' => ['type' => 'boolean', 'description' => 'Solo toques que tienen comentario.'],
                ]],
            ],
            [
                'name' => 'location_stats',
                'description' => 'Estadísticas por ubicación en un periodo: número de visitas, empleados distintos y última visita. Incluye ubicaciones sin visitas.',
                'inputSchema' => ['type' => 'object', 'properties' => $range],
            ],
            [
                'name' => 'complaints',
                'description' => 'Quejas registradas: código, fecha, asunto, categoría, prioridad, estado, ubicación, responsable y fecha de resolución.',
                'inputSchema' => ['type' => 'object', 'properties' => $range + [
                    'status' => ['type' => 'string', 'enum' => array_keys(Complaint::STATUSES), 'description' => 'open, in_progress, resolved o closed. Opcional.'],
                ]],
            ],
            [
                'name' => 'list_employees',
                'description' => 'Lista del personal con rol, departamento, cargo, horario personalizado y si está activo.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'department' => ['type' => 'string', 'description' => 'Departamento para filtrar. Opcional.'],
                ]],
            ],
            [
                'name' => 'list_locations',
                'description' => 'Lista de ubicaciones con área, piso y estado de su etiqueta NFC (grabada, bloqueada, activa).',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
        ];
    }

    /**
     * Ejecuta una herramienta y devuelve el resultado como JSON.
     *
     * @param  array<string, mixed>  $input
     * @return array{content: string, is_error: bool}
     */
    public function run(User $user, string $name, array $input): array
    {
        try {
            $result = match ($name) {
                'team_status_now' => $this->teamStatus($user),
                'attendance_records' => $this->attendanceRecords($user, $input),
                'attendance_summary' => $this->attendanceSummary($user, $input),
                'visits' => $this->visits($user, $input),
                'location_stats' => $this->locationStats($user, $input),
                'complaints' => $this->complaints($user, $input),
                'list_employees' => $this->employees($user, $input),
                'list_locations' => $this->locations(),
                default => throw new InvalidArgumentException("Herramienta desconocida: {$name}"),
            };

            return ['content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'is_error' => false];
        } catch (InvalidArgumentException $e) {
            return ['content' => $e->getMessage(), 'is_error' => true];
        } catch (Throwable $e) {
            report($e);

            return ['content' => 'Error interno al consultar los datos.', 'is_error' => true];
        }
    }

    private function seesEveryone(User $user): bool
    {
        return $user->hasPermission('ai.all_data');
    }

    /**
     * @return array<string, mixed>
     */
    private function teamStatus(User $user): array
    {
        $board = $this->team->board();
        $rows = collect($board['rows']);

        if (! $this->seesEveryone($user)) {
            return ['scope' => 'solo tus datos', 'me' => $rows->firstWhere('id', $user->id)];
        }

        return [
            'now' => now()->format('Y-m-d H:i'),
            'summary' => $board['summary'],
            'people' => $rows->map(fn (array $r) => collect($r)->only([
                'name', 'department', 'status_label', 'check_in', 'check_out', 'is_late', 'late_minutes', 'last_location', 'last_seen', 'scans_count', 'worked',
            ]))->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attendanceRecords(User $user, array $input): array
    {
        [$from, $to] = $this->range($input);

        $rows = Attendance::query()
            ->with(['user', 'checkInLocation', 'checkOutLocation'])
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->tap(fn (Builder $q) => $this->scopeUsers($q, $user, $input['employee'] ?? null))
            ->orderBy('date')
            ->orderBy('check_in_at')
            ->limit(self::MAX_ROWS + 1)
            ->get();

        return [
            'range' => [$from, $to],
            'truncated' => $rows->count() > self::MAX_ROWS,
            'records' => $rows->take(self::MAX_ROWS)->map(fn (Attendance $a) => [
                'date' => $a->date->toDateString(),
                'employee' => $a->user->name,
                'check_in' => $a->check_in_at->format('H:i'),
                'check_in_location' => $a->checkInLocation?->name,
                'check_out' => $a->check_out_at?->format('H:i'),
                'check_out_location' => $a->checkOutLocation?->name,
                'worked_hours' => round($a->worked_minutes / 60, 2),
                'late_minutes' => $a->late_minutes,
                'scans' => $a->scans_count,
                'notes' => $a->notes,
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attendanceSummary(User $user, array $input): array
    {
        [$from, $to] = $this->range($input);

        $userId = null;
        if (! $this->seesEveryone($user)) {
            $userId = $user->id;
        } elseif (filled($input['employee'] ?? null)) {
            $userId = $this->findEmployee((string) $input['employee'])->id;
        }

        $data = $this->reports->attendance([
            'from' => $from,
            'to' => $to,
            'user_id' => $userId,
            'department' => $this->seesEveryone($user) ? ($input['department'] ?? null) : null,
        ]);

        return [
            'range' => [$from, $to],
            'working_days_in_range' => $data['totals']['working_days'],
            'employees' => $data['employees']->map(fn (array $e) => [
                'employee' => $e['name'],
                'department' => $e['department'],
                'days_worked' => $e['days'],
                'absences' => $e['absences'],
                'late_days' => $e['late_days'],
                'late_minutes_total' => $e['late_minutes'],
                'worked_hours' => round($e['worked_minutes'] / 60, 2),
                'average_check_in' => $e['avg_check_in'],
                'punctuality_percent' => $e['punctuality'],
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function visits(User $user, array $input): array
    {
        [$from, $to] = $this->range($input);

        $rows = Scan::query()
            ->with(['user', 'location'])
            ->whereDate('scanned_at', '>=', $from)
            ->whereDate('scanned_at', '<=', $to)
            ->tap(fn (Builder $q) => $this->scopeUsers($q, $user, $input['employee'] ?? null))
            ->when(filled($input['location'] ?? null), fn (Builder $q) => $q->whereHas('location', fn (Builder $l) => $l->where('name', 'like', '%'.$input['location'].'%')))
            ->when(! empty($input['only_with_comments']), fn (Builder $q) => $q->whereNotNull('comment'))
            ->orderBy('scanned_at')
            ->limit(self::MAX_ROWS + 1)
            ->get();

        return [
            'range' => [$from, $to],
            'truncated' => $rows->count() > self::MAX_ROWS,
            'visits' => $rows->take(self::MAX_ROWS)->map(fn (Scan $s) => [
                'datetime' => $s->scanned_at->format('Y-m-d H:i'),
                'employee' => $s->user->name,
                'location' => $s->location->name,
                'type' => $s->typeLabel(),
                'comment' => $s->comment,
                'has_photo' => (bool) $s->photo_path,
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function locationStats(User $user, array $input): array
    {
        [$from, $to] = $this->range($input);

        $data = $this->reports->visits([
            'from' => $from,
            'to' => $to,
            'user_id' => $this->seesEveryone($user) ? null : $user->id,
        ]);

        $visited = $data['locations']->keyBy('name');

        return [
            'range' => [$from, $to],
            'locations' => Location::query()->orderBy('name')->get()->map(fn (Location $l) => [
                'location' => $l->name,
                'area' => $l->area,
                'visits' => $visited->get($l->name)['visits'] ?? 0,
                'distinct_employees' => $visited->get($l->name)['employees'] ?? 0,
                'last_visit' => isset($visited[$l->name]) ? $visited[$l->name]['last']?->format('Y-m-d H:i') : null,
            ])->sortByDesc('visits')->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function complaints(User $user, array $input): array
    {
        $hasRange = filled($input['date_from'] ?? null);
        [$from, $to] = $hasRange ? $this->range($input) : [null, null];

        $rows = Complaint::query()
            ->with(['location', 'assignee', 'user'])
            ->when(! $user->hasPermission('complaints.manage'), fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('assigned_to', $user->id)))
            ->when($from, fn (Builder $q) => $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to))
            ->when(filled($input['status'] ?? null), fn (Builder $q) => $q->where('status', $input['status']))
            ->latest()
            ->limit(self::MAX_ROWS)
            ->get();

        return [
            'range' => $from ? [$from, $to] : 'todas las fechas',
            'complaints' => $rows->map(fn (Complaint $c) => [
                'code' => $c->code,
                'created' => $c->created_at->format('Y-m-d H:i'),
                'subject' => $c->subject,
                'category' => $c->categoryLabel(),
                'priority' => $c->priorityLabel(),
                'status' => $c->statusLabel(),
                'location' => $c->location?->name,
                'reported_by' => $c->reporterName(),
                'assigned_to' => $c->assignee?->name,
                'resolved' => $c->resolved_at?->format('Y-m-d H:i'),
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function employees(User $user, array $input): array
    {
        $query = User::query()->orderBy('name');

        if (! $this->seesEveryone($user)) {
            $query->whereKey($user->id);
        } elseif (filled($input['department'] ?? null)) {
            $query->where('department', 'like', '%'.$input['department'].'%');
        }

        return [
            'employees' => $query->get()->map(fn (User $u) => [
                'name' => $u->name,
                'role' => $u->roleLabel(),
                'department' => $u->department,
                'position' => $u->position,
                'shift' => $u->shift_start ? substr($u->shift_start, 0, 5).'-'.substr((string) $u->shift_end, 0, 5) : 'horario general',
                'active' => $u->is_active,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function locations(): array
    {
        return [
            'locations' => Location::query()->orderBy('name')->get()->map(fn (Location $l) => [
                'name' => $l->name,
                'code' => $l->code,
                'area' => $l->area,
                'floor' => $l->floor,
                'active' => $l->is_active,
                'tag_written' => (bool) $l->written_at,
                'tag_locked' => $l->is_locked,
            ])->values(),
        ];
    }

    private function scopeUsers(Builder $query, User $user, mixed $employee): void
    {
        if (! $this->seesEveryone($user)) {
            $query->where('user_id', $user->id);

            return;
        }

        if (filled($employee)) {
            $query->where('user_id', $this->findEmployee((string) $employee)->id);
        }
    }

    private function findEmployee(string $name): User
    {
        $matches = User::query()->where('name', 'like', '%'.trim($name).'%')->limit(6)->get();

        if ($matches->isEmpty()) {
            throw new InvalidArgumentException("No encontré ningún empleado que coincida con «{$name}».");
        }

        if ($matches->count() > 1) {
            $exact = $matches->first(fn (User $u) => mb_strtolower($u->name) === mb_strtolower(trim($name)));
            if ($exact) {
                return $exact;
            }

            throw new InvalidArgumentException('Hay varios empleados que coinciden: '.$matches->pluck('name')->implode(', ').'. Pide al usuario que precise.');
        }

        return $matches->first();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: string}
     */
    private function range(array $input): array
    {
        $parse = function (mixed $value, string $fallback): string {
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $fallback;
            }

            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $value)->toDateString();
            } catch (Throwable) {
                throw new InvalidArgumentException("Fecha no válida: {$value}");
            }
        };

        $from = $parse($input['date_from'] ?? null, today()->toDateString());
        $to = $parse($input['date_to'] ?? null, $from);

        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 366) {
            throw new InvalidArgumentException('El rango máximo es de un año.');
        }

        return [$from, $to];
    }
}
