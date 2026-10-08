<x-app-layout>
    <x-slot name="title">Informe de asistencia</x-slot>
    <x-slot name="header">Informe de asistencia</x-slot>

    <x-filter-bar>
        <x-field label="Desde"><x-text-input type="date" name="desde" :value="$filters['from']" class="block w-full" /></x-field>
        <x-field label="Hasta"><x-text-input type="date" name="hasta" :value="$filters['to']" class="block w-full" /></x-field>
        <x-field label="Empleado"><x-select name="empleado" :options="$users" :selected="$filters['user_id']" placeholder="Todos" class="block w-full" /></x-field>
        <x-field label="Departamento"><x-select name="departamento" :options="$departments" :selected="$filters['department']" placeholder="Todos" class="block w-full" /></x-field>
    </x-filter-bar>

    @include('reports.partials.toolbar', ['reportTitle' => 'Informe de asistencia', 'csvTables' => ['Resumen', 'Detalle']])

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
        <x-stat label="Días laborables" :value="$totals['working_days']" icon="calendar-days" color="gray" />
        <x-stat label="Registros" :value="$totals['records']" icon="clipboard-document-check" />
        <x-stat label="Llegadas tarde" :value="$totals['late']" icon="clock" color="amber" />
        <x-stat label="Ausencias" :value="$totals['absences']" icon="x-circle" color="rose" />
        <x-stat label="Horas trabajadas" :value="number_format($totals['worked_minutes'] / 60, 1)" icon="chart-bar" color="sky" class="col-span-2 md:col-span-1" />
    </div>

    <x-card title="Resumen por empleado" :padding="false" class="mb-4">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr><th>Empleado</th><th>Departamento</th><th>Días</th><th>Ausencias</th><th>Tarde</th><th>Min. retraso</th><th>Horas</th><th>Entrada prom.</th><th>Puntualidad</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($employees as $e)
                        <tr>
                            <td class="font-medium text-gray-900">{{ $e['name'] }}</td>
                            <td>{{ $e['department'] ?? '—' }}</td>
                            <td class="tabular-nums">{{ $e['days'] }}</td>
                            <td @class(['tabular-nums', 'font-semibold text-rose-700' => $e['absences'] > 0])>{{ $e['absences'] }}</td>
                            <td @class(['tabular-nums', 'font-semibold text-amber-700' => $e['late_days'] > 0])>{{ $e['late_days'] }}</td>
                            <td class="tabular-nums">{{ $e['late_minutes'] }}</td>
                            <td class="tabular-nums">{{ \App\Models\Attendance::formatMinutes($e['worked_minutes']) }}</td>
                            <td class="tabular-nums">{{ $e['avg_check_in'] ?? '—' }}</td>
                            <td class="tabular-nums">{{ $e['punctuality'] !== null ? $e['punctuality'].'%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-gray-500">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="Detalle diario" :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr><th>Fecha</th><th>Empleado</th><th>Entrada</th><th>Salida</th><th>Horas</th><th>Estado</th><th>Toques</th><th>Notas</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $a)
                        <tr>
                            <td class="tabular-nums">{{ $a->date->format('d/m/Y') }}</td>
                            <td>{{ $a->user->name }}</td>
                            <td class="tabular-nums">{{ $a->check_in_at->format('H:i') }}</td>
                            <td class="tabular-nums">{{ $a->check_out_at?->format('H:i') ?? '—' }}</td>
                            <td class="tabular-nums">{{ $a->workedLabel() }}</td>
                            <td>{!! $a->is_late ? '<span class="text-amber-700">Tarde ('.(int) $a->late_minutes.' min)</span>' : 'A tiempo' !!}</td>
                            <td class="tabular-nums">{{ $a->scans_count }}</td>
                            <td class="max-w-xs truncate">{{ $a->notes }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-gray-500">Sin registros en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
