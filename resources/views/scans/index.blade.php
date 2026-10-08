<x-app-layout>
    <x-slot name="title">Visitas</x-slot>
    <x-slot name="header">Historial de visitas</x-slot>
    <x-slot name="actions">
        @can('reports.view')
            <x-button-link :href="route('reports.visits', request()->query())" variant="secondary" icon="chart-bar">Informe</x-button-link>
        @endcan
    </x-slot>

    <x-filter-bar>
        <x-field label="Desde"><x-text-input type="date" name="desde" :value="$filters['from']" class="block w-full" /></x-field>
        <x-field label="Hasta"><x-text-input type="date" name="hasta" :value="$filters['to']" class="block w-full" /></x-field>
        <x-field label="Empleado"><x-select name="empleado" :options="$users" :selected="$filters['user_id']" placeholder="Todos" class="block w-full" /></x-field>
        <x-field label="Ubicación"><x-select name="ubicacion" :options="$locations" :selected="$filters['location_id']" placeholder="Todas" class="block w-full" /></x-field>
        <x-field label="Tipo"><x-select name="tipo" :options="['check_in' => 'Entrada', 'visit' => 'Visita', 'check_out' => 'Salida']" :selected="$filters['type']" placeholder="Todos" class="block w-full" /></x-field>
        <x-slot name="extra">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar en comentarios…" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-80">
        </x-slot>
    </x-filter-bar>

    <x-card :padding="false">
        <ul class="divide-y divide-gray-100">
            @forelse ($scans as $scan)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
                    <x-avatar :user="$scan->user" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm"><span class="font-medium text-gray-900">{{ $scan->user->name }}</span> <span class="text-gray-500">en</span> <span class="text-gray-800">{{ $scan->location->name }}</span></p>
                        @if ($scan->comment)
                            <p class="truncate text-xs text-gray-500">“{{ $scan->comment }}”</p>
                        @endif
                    </div>
                    @if ($scan->photo_path)
                        <x-heroicon-o-camera class="h-4 w-4 text-gray-400" title="Con foto" />
                    @endif
                    <x-badge :color="$scan->typeColor()">{{ $scan->typeLabel() }}</x-badge>
                    <a href="{{ route('scans.receipt', $scan) }}" class="w-28 text-right text-xs tabular-nums text-gray-500 hover:text-indigo-600">{{ $scan->scanned_at->translatedFormat('d M · H:i') }}</a>
                </li>
            @empty
                <li><x-empty-state icon="map" title="Sin visitas" description="No hay toques registrados con estos filtros." /></li>
            @endforelse
        </ul>
    </x-card>
    <div class="mt-4">{{ $scans->links() }}</div>
</x-app-layout>
