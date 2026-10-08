<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Location;
use App\Models\Scan;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReportService;
use App\Support\SpreadsheetExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(): View
    {
        return view('reports.index');
    }

    public function attendance(Request $request): View|Response
    {
        $filters = $this->range($request, today()->startOfMonth()->toDateString()) + [
            'user_id' => $request->integer('empleado') ?: null,
            'department' => $request->string('departamento')->toString() ?: null,
        ];

        $data = $this->reports->attendance($filters);

        if ($format = $this->exportFormat($request)) {
            $summary = [
                'headers' => ['Empleado', 'Departamento', 'Días trabajados', 'Ausencias', 'Llegadas tarde', 'Minutos de retraso', 'Horas trabajadas', 'Entrada promedio', 'Puntualidad %'],
                'rows' => $data['employees']->map(fn (array $e) => [
                    $e['name'], $e['department'], $e['days'], $e['absences'], $e['late_days'], $e['late_minutes'],
                    round($e['worked_minutes'] / 60, 2), $e['avg_check_in'], $e['punctuality'],
                ]),
            ];
            $detail = [
                'headers' => ['Fecha', 'Empleado', 'Departamento', 'Entrada', 'Ubicación entrada', 'Salida', 'Ubicación salida', 'Horas', 'Tarde', 'Minutos tarde', 'Toques', 'Notas'],
                'rows' => $data['rows']->map(fn (Attendance $a) => [
                    $a->date->toDateString(), $a->user->name, $a->user->department, $a->check_in_at->format('H:i'),
                    $a->checkInLocation?->name, $a->check_out_at?->format('H:i'), $a->checkOutLocation?->name,
                    round($a->worked_minutes / 60, 2), $a->is_late, $a->late_minutes, $a->scans_count, $a->notes,
                ]),
            ];

            return $this->export($format, $request, 'asistencia', $filters, ['Resumen' => $summary, 'Detalle' => $detail]);
        }

        return view('reports.attendance', $data + [
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'departments' => User::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department', 'department'),
        ] + $this->printMeta($filters));
    }

    public function visits(Request $request): View|Response
    {
        $filters = $this->range($request, today()->subDays(6)->toDateString()) + [
            'user_id' => $request->integer('empleado') ?: null,
            'location_id' => $request->integer('ubicacion') ?: null,
        ];

        $data = $this->reports->visits($filters);

        if ($format = $this->exportFormat($request)) {
            $byLocation = [
                'headers' => ['Ubicación', 'Área', 'Visitas', 'Empleados distintos', 'Con comentario', 'Última visita'],
                'rows' => $data['locations']->map(fn (array $l) => [$l['name'], $l['area'], $l['visits'], $l['employees'], $l['comments'], $l['last']?->format('Y-m-d H:i')]),
            ];
            $byEmployee = [
                'headers' => ['Empleado', 'Departamento', 'Visitas', 'Ubicaciones distintas', 'Días'],
                'rows' => $data['employees']->map(fn (array $e) => [$e['name'], $e['department'], $e['visits'], $e['locations'], $e['days']]),
            ];
            $detail = [
                'headers' => ['Fecha', 'Hora', 'Empleado', 'Ubicación', 'Tipo', 'Método', 'Comentario'],
                'rows' => $data['rows']->map(fn (Scan $s) => [
                    $s->scanned_at->toDateString(), $s->scanned_at->format('H:i:s'), $s->user->name, $s->location->name,
                    $s->typeLabel(), $s->sourceLabel(), $s->comment,
                ]),
            ];

            return $this->export($format, $request, 'visitas', $filters, ['Por ubicación' => $byLocation, 'Por empleado' => $byEmployee, 'Detalle' => $detail]);
        }

        return view('reports.visits', $data + [
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'locationOptions' => Location::query()->orderBy('name')->pluck('name', 'id'),
        ] + $this->printMeta($filters));
    }

    public function complaints(Request $request): View|Response
    {
        $filters = $this->range($request, today()->startOfMonth()->toDateString()) + [
            'status' => $request->string('estado')->toString() ?: null,
            'category' => $request->string('categoria')->toString() ?: null,
            'priority' => $request->string('prioridad')->toString() ?: null,
        ];

        $data = $this->reports->complaints($filters);

        if ($format = $this->exportFormat($request)) {
            $detail = [
                'headers' => ['Código', 'Fecha', 'Asunto', 'Categoría', 'Prioridad', 'Estado', 'Ubicación', 'Reportada por', 'Responsable', 'Resuelta', 'Horas hasta resolver'],
                'rows' => $data['rows']->map(fn (Complaint $c) => [
                    $c->code, $c->created_at->format('Y-m-d H:i'), $c->subject, $c->categoryLabel(), $c->priorityLabel(), $c->statusLabel(),
                    $c->location?->name, $c->reporterName(), $c->assignee?->name, $c->resolved_at?->format('Y-m-d H:i'),
                    $c->resolved_at ? round($c->created_at->diffInMinutes($c->resolved_at) / 60, 1) : null,
                ]),
            ];
            $summary = [
                'headers' => ['Indicador', 'Valor', 'Cantidad'],
                'rows' => collect()
                    ->merge(collect($data['by_status'])->map(fn ($n, $label) => ['Estado', $label, $n]))
                    ->merge(collect($data['by_priority'])->map(fn ($n, $label) => ['Prioridad', $label, $n]))
                    ->merge(collect($data['by_category'])->map(fn ($n, $label) => ['Categoría', $label, $n]))
                    ->values(),
            ];

            return $this->export($format, $request, 'quejas', $filters, ['Detalle' => $detail, 'Resumen' => $summary]);
        }

        return view('reports.complaints', $data + ['filters' => $filters] + $this->printMeta($filters));
    }

    /**
     * @return array{from: string, to: string}
     */
    private function range(Request $request, string $defaultFrom): array
    {
        $from = $request->date('desde')?->toDateString() ?? $defaultFrom;
        $to = $request->date('hasta')?->toDateString() ?? today()->toDateString();

        return ['from' => $from, 'to' => $to < $from ? $from : $to];
    }

    private function exportFormat(Request $request): ?string
    {
        $format = $request->string('exportar')->toString();

        return in_array($format, ['csv', 'xlsx'], true) ? $format : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, array{headers: list<string>, rows: iterable<array<int, mixed>>}>  $sheets
     */
    private function export(string $format, Request $request, string $name, array $filters, array $sheets): Response
    {
        $filename = "informe-{$name}-{$filters['from']}-a-{$filters['to']}";

        if ($format === 'xlsx') {
            return SpreadsheetExporter::xlsx($filename, $sheets);
        }

        // CSV: una sola tabla (?tabla=Resumen|Detalle...). Por defecto, la primera.
        $table = $request->string('tabla')->toString();
        $sheet = $sheets[$table] ?? reset($sheets);

        return SpreadsheetExporter::csv($filename.($table ? '-'.str($table)->slug() : ''), $sheet['headers'], $sheet['rows']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function printMeta(array $filters): array
    {
        return [
            'companyName' => (string) Setting::get('company_name'),
            'period' => Carbon::parse($filters['from'])->translatedFormat('d M Y').' – '.Carbon::parse($filters['to'])->translatedFormat('d M Y'),
        ];
    }
}
