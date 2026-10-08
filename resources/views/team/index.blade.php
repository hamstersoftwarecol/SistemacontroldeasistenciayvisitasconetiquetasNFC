<x-app-layout>
    <x-slot name="title">Equipo en vivo</x-slot>
    <x-slot name="header">Equipo en tiempo real</x-slot>

    <div x-data="teamBoard({ url: @js(route('team.status')), initial: @js($board) })" class="space-y-4 bg-gray-50">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <x-stat label="Personal" bind="summary.total" icon="users" color="gray" />
            <x-stat label="Presentes" bind="summary.present" icon="check-circle" color="emerald" />
            <x-stat label="En turno ahora" bind="summary.active" icon="signal" />
            <x-stat label="Tarde" bind="summary.late" icon="clock" color="amber" />
            <x-stat label="Sin registrar" bind="summary.absent" icon="x-circle" color="rose" />
            <x-stat label="Visitas hoy" bind="summary.visits" icon="map-pin" color="violet" />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                <input type="search" x-model="search" placeholder="Buscar por nombre, área o ubicación…" class="w-full rounded-lg border-gray-300 pl-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="flex gap-1 overflow-x-auto rounded-lg bg-white p-1 shadow-sm ring-1 ring-gray-200">
                @foreach (['all' => 'Todos', 'active' => 'En turno', 'idle' => 'Inactivos', 'late' => 'Tarde', 'absent' => 'Sin registrar'] as $key => $label)
                    <button type="button" @click="status = '{{ $key }}'" :class="status === '{{ $key }}' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium">{{ $label }}</button>
                @endforeach
            </div>
            <p class="flex items-center gap-2 text-xs text-gray-500">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" x-show="!failed"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full" :class="failed ? 'bg-rose-500' : 'bg-emerald-500'"></span>
                </span>
                <span x-text="failed ? 'Sin conexión' : 'Actualizado ' + (summary.updated_at ?? '')"></span>
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <template x-for="row in filtered" :key="row.id">
                <div class="rounded-2xl border bg-white p-4 shadow-sm"
                     :class="{ 'border-emerald-200': row.status === 'active', 'border-gray-200': row.status !== 'active' }">
                    <div class="flex items-start gap-3">
                        <div class="relative">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-semibold"
                                  :class="{ 'bg-emerald-100 text-emerald-700': row.status === 'active', 'bg-amber-100 text-amber-700': row.status === 'idle', 'bg-gray-100 text-gray-500': row.status === 'absent' }"
                                  x-text="row.initials"></span>
                            <span x-show="row.online" class="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-emerald-500" title="Conectado a la app"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-gray-900" x-text="row.name"></p>
                            <p class="truncate text-xs text-gray-500" x-text="[row.position, row.department].filter(Boolean).join(' · ') || row.role"></p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                              :class="{ 'bg-emerald-50 text-emerald-700 ring-emerald-600/20': row.status === 'active', 'bg-amber-50 text-amber-800 ring-amber-600/20': row.status === 'idle', 'bg-gray-50 text-gray-600 ring-gray-500/20': row.status === 'absent' }"
                              x-text="row.status_label"></span>
                    </div>
                    <template x-if="row.status !== 'absent'">
                        <div class="mt-3 space-y-2 text-sm">
                            <div class="flex items-center gap-2 text-gray-700">
                                <x-heroicon-o-map-pin class="h-4 w-4 shrink-0 text-gray-400" />
                                <span class="truncate" x-text="row.last_location ?? '—'"></span>
                                <span class="ml-auto shrink-0 text-xs text-gray-500" x-text="row.last_seen_human"></span>
                            </div>
                            <div class="grid grid-cols-4 gap-2 rounded-xl bg-gray-50 p-2 text-center text-xs">
                                <div><p class="text-gray-500">Entrada</p><p class="font-semibold tabular-nums" :class="row.is_late ? 'text-amber-700' : 'text-gray-900'" x-text="row.check_in ?? '—'"></p></div>
                                <div><p class="text-gray-500">Último</p><p class="font-semibold tabular-nums text-gray-900" x-text="row.last_seen ?? '—'"></p></div>
                                <div><p class="text-gray-500">Tiempo</p><p class="font-semibold tabular-nums text-gray-900" x-text="row.worked ?? '—'"></p></div>
                                <div><p class="text-gray-500">Toques</p><p class="font-semibold tabular-nums text-gray-900" x-text="row.scans_count"></p></div>
                            </div>
                            <p x-show="row.is_late" class="text-xs text-amber-700" x-text="'Llegó ' + row.late_minutes + ' min tarde'"></p>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div x-show="!filtered.length" class="rounded-2xl border border-dashed border-gray-300 bg-white">
            <x-empty-state icon="user-group" title="Sin resultados" description="No hay personas que coincidan con los filtros." />
        </div>
    </div>
</x-app-layout>
