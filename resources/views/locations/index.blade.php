<x-app-layout>
    <x-slot name="title">Ubicaciones</x-slot>
    <x-slot name="header">Ubicaciones</x-slot>
    <x-slot name="actions">
        @can('nfc.write')
            <x-button-link :href="route('nfc.writer')" variant="secondary" icon="pencil-square">Escritor NFC</x-button-link>
        @endcan
        <x-button-link :href="route('locations.create')" icon="plus">Nueva ubicación</x-button-link>
    </x-slot>

    <form method="GET" class="mb-4">
        <div class="relative">
            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Buscar por nombre, código o área…" class="w-full rounded-lg border-gray-300 pl-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </form>

    @if ($locations->count())
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($locations as $location)
                <a href="{{ route('locations.show', $location) }}" class="group rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-900 group-hover:text-indigo-700">{{ $location->name }}</p>
                            <p class="truncate text-sm text-gray-500">{{ collect([$location->area, $location->floor ? 'Piso '.$location->floor : null])->filter()->implode(' · ') ?: 'Sin área' }}</p>
                        </div>
                        <span class="shrink-0 rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-600">{{ $location->code }}</span>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if (! $location->is_active)
                            <x-badge color="rose">Desactivada</x-badge>
                        @endif
                        @if ($location->is_locked)
                            <x-badge color="violet"><x-heroicon-o-lock-closed class="h-3 w-3" /> Bloqueada</x-badge>
                        @elseif ($location->written_at)
                            <x-badge color="emerald">Etiqueta grabada</x-badge>
                        @else
                            <x-badge color="amber">Sin grabar</x-badge>
                        @endif
                        <span class="ml-auto text-xs text-gray-500">{{ $location->scans_today }} toques hoy</span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $locations->links() }}</div>
    @else
        <x-card>
            <x-empty-state icon="map-pin" title="No hay ubicaciones" description="Crea ubicaciones (habitaciones, oficinas, entradas…) y graba una etiqueta NFC para cada una.">
                <x-button-link :href="route('locations.create')" class="mt-4" icon="plus">Nueva ubicación</x-button-link>
            </x-empty-state>
        </x-card>
    @endif
</x-app-layout>
