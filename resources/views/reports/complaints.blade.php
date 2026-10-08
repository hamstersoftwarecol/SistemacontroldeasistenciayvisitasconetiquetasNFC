<x-app-layout>
    <x-slot name="title">Informe de quejas</x-slot>
    <x-slot name="header">Informe de quejas</x-slot>

    <x-filter-bar>
        <x-field label="Desde"><x-text-input type="date" name="desde" :value="$filters['from']" class="block w-full" /></x-field>
        <x-field label="Hasta"><x-text-input type="date" name="hasta" :value="$filters['to']" class="block w-full" /></x-field>
        <x-field label="Estado"><x-select name="estado" :options="\App\Models\Complaint::STATUSES" :selected="$filters['status']" placeholder="Todos" class="block w-full" /></x-field>
        <x-field label="Categoría"><x-select name="categoria" :options="\App\Models\Complaint::CATEGORIES" :selected="$filters['category']" placeholder="Todas" class="block w-full" /></x-field>
        <x-field label="Prioridad"><x-select name="prioridad" :options="\App\Models\Complaint::PRIORITIES" :selected="$filters['priority']" placeholder="Todas" class="block w-full" /></x-field>
    </x-filter-bar>

    @include('reports.partials.toolbar', ['reportTitle' => 'Informe de quejas', 'csvTables' => ['Detalle', 'Resumen']])

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-stat label="Quejas" :value="$totals['total']" icon="flag" />
        <x-stat label="Abiertas" :value="$totals['open']" icon="exclamation-circle" color="amber" />
        <x-stat label="Resueltas" :value="$totals['resolved']" icon="check-circle" color="emerald" />
        <x-stat label="Horas prom. resolución" :value="$totals['avg_hours'] ?? '—'" icon="clock" color="sky" />
    </div>

    <div class="mb-4 grid gap-4 md:grid-cols-3">
        @foreach (['Por estado' => $by_status, 'Por prioridad' => $by_priority, 'Por categoría' => $by_category] as $title => $group)
            <x-card :title="$title">
                @php $max = max(1, max($group ?: [0])); @endphp
                <ul class="space-y-2">
                    @forelse ($group as $label => $count)
                        <li class="text-sm">
                            <div class="flex justify-between"><span class="text-gray-700">{{ $label }}</span><span class="font-semibold tabular-nums">{{ $count }}</span></div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full" style="width: {{ round($count / $max * 100) }}%; background: #2a78d6"></div></div>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">Sin datos.</li>
                    @endforelse
                </ul>
            </x-card>
        @endforeach
    </div>

    <x-card title="Detalle" :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Código</th><th>Fecha</th><th>Asunto</th><th>Categoría</th><th>Prioridad</th><th>Estado</th><th>Ubicación</th><th>Responsable</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $c)
                        <tr>
                            <td class="font-mono text-xs"><a href="{{ route('complaints.show', $c) }}" class="text-indigo-600 print:text-gray-900">{{ $c->code }}</a></td>
                            <td class="tabular-nums">{{ $c->created_at->format('d/m/Y') }}</td>
                            <td class="max-w-xs truncate">{{ $c->subject }}</td>
                            <td>{{ $c->categoryLabel() }}</td>
                            <td>{{ $c->priorityLabel() }}</td>
                            <td>{{ $c->statusLabel() }}</td>
                            <td>{{ $c->location?->name ?? '—' }}</td>
                            <td>{{ $c->assignee?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-gray-500">Sin quejas en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
