<x-app-layout>
    <x-slot name="title">Informe de visitas</x-slot>
    <x-slot name="header">Informe de visitas</x-slot>

    <x-filter-bar>
        <x-field label="Desde"><x-text-input type="date" name="desde" :value="$filters['from']" class="block w-full" /></x-field>
        <x-field label="Hasta"><x-text-input type="date" name="hasta" :value="$filters['to']" class="block w-full" /></x-field>
        <x-field label="Empleado"><x-select name="empleado" :options="$users" :selected="$filters['user_id']" placeholder="Todos" class="block w-full" /></x-field>
        <x-field label="Ubicación"><x-select name="ubicacion" :options="$locationOptions" :selected="$filters['location_id']" placeholder="Todas" class="block w-full" /></x-field>
    </x-filter-bar>

    @include('reports.partials.toolbar', ['reportTitle' => 'Informe de visitas', 'csvTables' => ['Por ubicación', 'Por empleado', 'Detalle']])

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-stat label="Visitas" :value="$totals['visits']" icon="map-pin" />
        <x-stat label="Ubicaciones" :value="$totals['locations']" icon="map" color="violet" />
        <x-stat label="Empleados" :value="$totals['employees']" icon="users" color="sky" />
        <x-stat label="Con comentario" :value="$totals['comments']" icon="chat-bubble-bottom-center-text" color="emerald" />
    </div>

    <div class="mb-4 grid gap-4 lg:grid-cols-2">
        <x-card title="Por ubicación" :padding="false">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Ubicación</th><th>Visitas</th><th>Empleados</th><th>Última</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($locations as $l)
                            <tr>
                                <td><span class="font-medium text-gray-900">{{ $l['name'] }}</span> <span class="text-xs text-gray-500">{{ $l['area'] }}</span></td>
                                <td class="tabular-nums">{{ $l['visits'] }}</td>
                                <td class="tabular-nums">{{ $l['employees'] }}</td>
                                <td class="tabular-nums">{{ $l['last']?->format('d/m H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-gray-500">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
        <x-card title="Por empleado" :padding="false">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Empleado</th><th>Visitas</th><th>Ubicaciones</th><th>Días</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($employees as $e)
                            <tr>
                                <td class="font-medium text-gray-900">{{ $e['name'] }}</td>
                                <td class="tabular-nums">{{ $e['visits'] }}</td>
                                <td class="tabular-nums">{{ $e['locations'] }}</td>
                                <td class="tabular-nums">{{ $e['days'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-gray-500">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <x-card title="Detalle de toques" :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Fecha</th><th>Hora</th><th>Empleado</th><th>Ubicación</th><th>Tipo</th><th>Comentario</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows->take(1000) as $s)
                        <tr>
                            <td class="tabular-nums">{{ $s->scanned_at->format('d/m/Y') }}</td>
                            <td class="tabular-nums">{{ $s->scanned_at->format('H:i') }}</td>
                            <td>{{ $s->user->name }}</td>
                            <td>{{ $s->location->name }}</td>
                            <td>{{ $s->typeLabel() }}</td>
                            <td class="max-w-xs truncate">{{ $s->comment }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-gray-500">Sin toques en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->count() > 1000)
            <p class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500">Se muestran 1.000 de {{ $rows->count() }} toques. Exporta a Excel para ver todos.</p>
        @endif
    </x-card>
</x-app-layout>
