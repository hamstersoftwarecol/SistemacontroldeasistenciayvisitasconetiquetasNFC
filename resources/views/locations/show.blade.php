<x-app-layout>
    <x-slot name="title">{{ $location->name }}</x-slot>
    <x-slot name="header">{{ $location->name }}</x-slot>
    <x-slot name="actions">
        @can('nfc.write')
            <x-button-link :href="route('nfc.writer', ['ubicacion' => $location->id])" icon="pencil-square">Grabar etiqueta</x-button-link>
        @endcan
        <x-button-link :href="route('locations.edit', $location)" variant="secondary" icon="pencil">Editar</x-button-link>
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-stat label="Toques hoy" :value="$stats['today']" icon="signal" />
                <x-stat label="Esta semana" :value="$stats['week']" icon="calendar-days" color="sky" />
                <x-stat label="Total" :value="$stats['total']" icon="chart-bar" color="violet" />
                <x-stat label="Quejas abiertas" :value="$stats['complaints']" icon="flag" color="amber" />
            </div>

            <x-card title="Últimas visitas">
                <x-slot name="headerActions">
                    @can('attendance.view_all')
                        <a href="{{ route('scans.index', ['ubicacion' => $location->id, 'desde' => today()->subDays(30)->toDateString()]) }}" class="text-sm font-medium text-indigo-600">Ver todas</a>
                    @endcan
                </x-slot>
                <ul class="divide-y divide-gray-100">
                    @forelse ($recent as $scan)
                        <li class="flex items-center gap-3 py-2 text-sm">
                            <x-avatar :user="$scan->user" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-gray-900">{{ $scan->user->name }}</p>
                                @if ($scan->comment)
                                    <p class="truncate text-xs text-gray-500">“{{ $scan->comment }}”</p>
                                @endif
                            </div>
                            <x-badge :color="$scan->typeColor()">{{ $scan->typeLabel() }}</x-badge>
                            <span class="w-24 text-right text-xs tabular-nums text-gray-500">{{ $scan->scanned_at->translatedFormat('d M H:i') }}</span>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-500">Nadie ha marcado en esta ubicación todavía.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Etiqueta NFC">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Estado</dt>
                        <dd>
                            @if (! $location->is_active)
                                <x-badge color="rose">Desactivada</x-badge>
                            @elseif ($location->is_locked)
                                <x-badge color="violet">Bloqueada (solo lectura)</x-badge>
                            @elseif ($location->written_at)
                                <x-badge color="emerald">Grabada</x-badge>
                            @else
                                <x-badge color="amber">Sin grabar</x-badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Código</dt><dd class="font-mono">{{ $location->code }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">UID de la etiqueta</dt><dd class="font-mono text-xs">{{ $location->tag_uid ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Grabada</dt><dd>{{ $location->written_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Bloqueada</dt><dd>{{ $location->locked_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
                </dl>
                <div class="mt-4 space-y-1" x-data="{ copied: false }">
                    <p class="text-xs font-medium text-gray-500">URL para grabar en la etiqueta</p>
                    <div class="flex gap-2">
                        <input type="text" readonly value="{{ $location->tapUrl() }}" class="min-w-0 flex-1 rounded-md border-gray-300 bg-gray-50 font-mono text-xs" @focus="$event.target.select()">
                        <button type="button" class="rounded-md bg-gray-100 px-2 text-gray-600 hover:bg-gray-200" @click="navigator.clipboard.writeText(@js($location->tapUrl())); copied = true; setTimeout(() => copied = false, 2000)" title="Copiar">
                            <x-heroicon-o-clipboard-document class="h-4 w-4" x-show="!copied" />
                            <x-heroicon-o-check class="h-4 w-4 text-emerald-600" x-show="copied" x-cloak />
                        </button>
                    </div>
                </div>
                <div class="mt-3 space-y-1">
                    <p class="text-xs font-medium text-gray-500">Formulario público de quejas</p>
                    <a href="{{ $location->complaintUrl() }}" target="_blank" class="block truncate font-mono text-xs text-indigo-600 hover:underline">{{ $location->complaintUrl() }}</a>
                </div>
                <form method="POST" action="{{ route('locations.regenerate', $location) }}" class="mt-4" onsubmit="return confirm('Las etiquetas grabadas actualmente dejarán de funcionar. ¿Generar un nuevo enlace?')">
                    @csrf
                    <x-button variant="secondary" icon="arrow-path" class="w-full">Regenerar enlace (invalidar etiqueta)</x-button>
                </form>
            </x-card>

            @if ($location->description)
                <x-card title="Descripción">
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ $location->description }}</p>
                </x-card>
            @endif

            <form method="POST" action="{{ route('locations.destroy', $location) }}" onsubmit="return confirm('Se eliminará la ubicación y todo su historial de visitas. ¿Continuar?')">
                @csrf
                @method('DELETE')
                <x-button variant="ghost" icon="trash" class="w-full text-rose-600">Eliminar ubicación</x-button>
            </form>
        </div>
    </div>
</x-app-layout>
