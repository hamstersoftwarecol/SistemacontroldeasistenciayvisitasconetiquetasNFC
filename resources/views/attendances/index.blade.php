<x-app-layout>
    <x-slot name="title">Asistencias</x-slot>
    <x-slot name="header">Historial de asistencia</x-slot>
    <x-slot name="actions">
        @can('reports.view')
            <x-button-link :href="route('reports.attendance', request()->query())" variant="secondary" icon="chart-bar">Informe</x-button-link>
        @endcan
        @can('attendance.manage')
            <x-button-link :href="route('attendances.create')" icon="plus">Registro manual</x-button-link>
        @endcan
    </x-slot>

    <x-filter-bar>
        <x-field label="Desde"><x-text-input type="date" name="desde" :value="$filters['from']" class="block w-full" /></x-field>
        <x-field label="Hasta"><x-text-input type="date" name="hasta" :value="$filters['to']" class="block w-full" /></x-field>
        <x-field label="Empleado"><x-select name="empleado" :options="$users" :selected="$filters['user_id']" placeholder="Todos" class="block w-full" /></x-field>
        <x-field label="Departamento"><x-select name="departamento" :options="$departments" :selected="$filters['department']" placeholder="Todos" class="block w-full" /></x-field>
        <x-field label="Estado"><x-select name="estado" :options="['on_time' => 'A tiempo', 'late' => 'Tarde', 'open' => 'Un solo toque']" :selected="$filters['status']" placeholder="Todos" class="block w-full" /></x-field>
    </x-filter-bar>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Empleado</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Horas</th>
                        <th>Toques</th>
                        <th>Estado</th>
                        <th class="print:hidden"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($attendances as $attendance)
                        <tr>
                            <td>{{ $attendance->date->translatedFormat('D d M Y') }}</td>
                            <td>
                                <p class="font-medium text-gray-900">{{ $attendance->user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $attendance->user->department }}</p>
                            </td>
                            <td class="tabular-nums">{{ $attendance->check_in_at->format('H:i') }} <span class="text-xs text-gray-500">{{ $attendance->checkInLocation?->name }}</span></td>
                            <td class="tabular-nums">{{ $attendance->check_out_at?->format('H:i') ?? '—' }} <span class="text-xs text-gray-500">{{ $attendance->checkOutLocation?->name }}</span></td>
                            <td class="tabular-nums">{{ $attendance->workedLabel() }}</td>
                            <td class="tabular-nums">{{ $attendance->scans_count }}</td>
                            <td>
                                @if ($attendance->is_late)
                                    <x-badge color="amber">Tarde · {{ $attendance->late_minutes }} min</x-badge>
                                @else
                                    <x-badge color="emerald">A tiempo</x-badge>
                                @endif
                            </td>
                            <td class="text-right print:hidden">
                                <a href="{{ route('attendances.show', $attendance) }}" class="font-medium text-indigo-600 hover:text-indigo-500">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="clipboard-document-check" title="Sin registros" description="No hay asistencias con estos filtros." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    <div class="mt-4">{{ $attendances->links() }}</div>
</x-app-layout>
