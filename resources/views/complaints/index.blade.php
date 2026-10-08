<x-app-layout>
    <x-slot name="title">Quejas</x-slot>
    <x-slot name="header">{{ $manager ? 'Gestión de quejas' : 'Mis quejas' }}</x-slot>
    <x-slot name="actions">
        @can('reports.view')
            <x-button-link :href="route('reports.complaints')" variant="secondary" icon="chart-bar">Informe</x-button-link>
        @endcan
        <x-button-link :href="route('complaints.create')" icon="plus">Nueva queja</x-button-link>
    </x-slot>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach (\App\Models\Complaint::STATUSES as $key => $label)
            <a href="{{ route('complaints.index', ['estado' => $key]) }}" @class(['rounded-2xl border bg-white p-4 shadow-sm hover:border-indigo-300', 'border-indigo-400 ring-1 ring-indigo-400' => $filters['status'] === $key, 'border-gray-200' => $filters['status'] !== $key])>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p>
                <p class="text-2xl font-bold tabular-nums">{{ $counts[$key] ?? 0 }}</p>
            </a>
        @endforeach
    </div>

    <x-filter-bar>
        <x-field label="Estado"><x-select name="estado" :options="\App\Models\Complaint::STATUSES" :selected="$filters['status']" placeholder="Todos" class="block w-full" /></x-field>
        <x-field label="Prioridad"><x-select name="prioridad" :options="\App\Models\Complaint::PRIORITIES" :selected="$filters['priority']" placeholder="Todas" class="block w-full" /></x-field>
        <x-field label="Categoría"><x-select name="categoria" :options="\App\Models\Complaint::CATEGORIES" :selected="$filters['category']" placeholder="Todas" class="block w-full" /></x-field>
        <x-field label="Buscar" class="col-span-2"><x-text-input type="search" name="q" :value="$filters['q']" placeholder="Código, asunto o texto…" class="block w-full" /></x-field>
        @if ($manager)
            <x-slot name="extra">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="asignadas" value="1" @checked($filters['mine']) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"> Solo asignadas a mí
                </label>
            </x-slot>
        @endif
    </x-filter-bar>

    <x-card :padding="false">
        <ul class="divide-y divide-gray-100">
            @forelse ($complaints as $complaint)
                <li>
                    <a href="{{ route('complaints.show', $complaint) }}" class="flex flex-col gap-2 px-4 py-3 hover:bg-gray-50 sm:flex-row sm:items-center sm:px-5">
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2">
                                <span class="font-mono text-xs text-gray-500">{{ $complaint->code }}</span>
                                <span class="truncate font-medium text-gray-900">{{ $complaint->subject }}</span>
                            </p>
                            <p class="mt-0.5 truncate text-xs text-gray-500">
                                {{ $complaint->categoryLabel() }}
                                @if ($complaint->location) · {{ $complaint->location->name }} @endif
                                · {{ $complaint->reporterName() }} · {{ $complaint->created_at->diffForHumans() }}
                                @if ($complaint->assignee) · Asignada a {{ $complaint->assignee->name }} @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-badge :color="$complaint->priorityColor()">{{ $complaint->priorityLabel() }}</x-badge>
                            <x-badge :color="$complaint->statusColor()">{{ $complaint->statusLabel() }}</x-badge>
                        </div>
                    </a>
                </li>
            @empty
                <li><x-empty-state icon="flag" title="No hay quejas" description="Cuando se reporte un problema aparecerá aquí." /></li>
            @endforelse
        </ul>
    </x-card>
    <div class="mt-4">{{ $complaints->links() }}</div>
</x-app-layout>
