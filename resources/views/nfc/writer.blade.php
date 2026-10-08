<x-app-layout>
    <x-slot name="title">Escritor NFC</x-slot>
    <x-slot name="header">Escritor de etiquetas NFC</x-slot>

    <div x-data="nfcWriter({ locations: @js($locations), identifyUrl: @js(route('nfc.identify')) })"
         x-init="@if ($preselected) select(locations.find(l => l.id === {{ (int) $preselected }}) ?? null) @endif"
         class="grid gap-4 lg:grid-cols-5">

        <div class="lg:col-span-3">
            <template x-if="!supported">
                <div class="mb-4 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                    <div>
                        <p class="font-semibold">Este navegador no puede escribir etiquetas NFC.</p>
                        <p class="mt-1">Abre esta página en <strong>Chrome para Android</strong> con NFC activado. En iPhone u otros equipos, copia la URL de la ubicación y grábala con una app como <em>NFC Tools</em> (registro tipo URL).</p>
                    </div>
                </div>
            </template>

            <x-card title="1. Elige la ubicación" :padding="false">
                <div class="border-b border-gray-100 p-3">
                    <input type="search" x-model="search" placeholder="Buscar ubicación…" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <ul class="max-h-[28rem] divide-y divide-gray-100 overflow-y-auto">
                    <template x-for="location in filtered" :key="location.id">
                        <li>
                            <button type="button" @click="select(location)" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50"
                                    :class="selected?.id === location.id ? 'bg-indigo-50' : ''">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                      :class="location.is_locked ? 'bg-violet-100 text-violet-700' : (location.written_at ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500')">
                                    <x-heroicon-o-lock-closed class="h-5 w-5" x-show="location.is_locked" />
                                    <x-heroicon-o-check class="h-5 w-5" x-show="!location.is_locked && location.written_at" />
                                    <x-heroicon-o-map-pin class="h-5 w-5" x-show="!location.is_locked && !location.written_at" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-gray-900" x-text="location.name"></span>
                                    <span class="block truncate text-xs text-gray-500" x-text="location.is_locked ? 'Bloqueada · ' + location.locked_at : (location.written_at ? 'Grabada · ' + location.written_at : 'Sin grabar')"></span>
                                </span>
                                <span class="font-mono text-xs text-gray-500" x-text="location.code"></span>
                            </button>
                        </li>
                    </template>
                </ul>
                <p x-show="!filtered.length" class="p-6 text-center text-sm text-gray-500">No hay ubicaciones. <a href="{{ route('locations.create') }}" class="text-indigo-600">Crea una</a>.</p>
            </x-card>
        </div>

        <div class="space-y-4 lg:col-span-2">
            <x-card title="2. Graba la etiqueta">
                <template x-if="!selected">
                    <p class="text-sm text-gray-500">Selecciona una ubicación de la lista.</p>
                </template>
                <template x-if="selected">
                    <div class="space-y-4">
                        <div>
                            <p class="text-lg font-semibold" x-text="selected.name"></p>
                            <p class="break-all font-mono text-xs text-gray-500" x-text="selected.url"></p>
                        </div>

                        <div x-show="selected.is_locked" class="rounded-lg bg-violet-50 p-3 text-sm text-violet-800">
                            La etiqueta de esta ubicación ya está bloqueada. Para cambiarla, regenera el enlace en la ficha de la ubicación y graba una etiqueta nueva.
                        </div>

                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" x-model="lockAfterWrite" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>Bloquear la etiqueta después de grabarla <span class="text-gray-500">(permanente: evita que alguien la reescriba)</span></span>
                        </label>

                        <div class="grid gap-2">
                            <x-button type="button" @click="write()" ::disabled="!supported || working" icon="pencil-square" class="py-3">
                                <span x-text="working ? 'Esperando etiqueta…' : 'Grabar etiqueta'"></span>
                            </x-button>
                            <div class="grid grid-cols-2 gap-2">
                                <x-button type="button" variant="secondary" @click="lock()" ::disabled="!supported || working" icon="lock-closed">Solo bloquear</x-button>
                                <x-button type="button" variant="secondary" @click="copyUrl(selected)" icon="clipboard-document">Copiar URL</x-button>
                            </div>
                            <x-button type="button" variant="ghost" x-show="working" @click="cancel(); setStatus('Operación cancelada.')" icon="x-mark">Cancelar</x-button>
                        </div>
                    </div>
                </template>

                <p x-show="status" x-cloak x-text="status" class="mt-4 rounded-lg px-3 py-2 text-sm" :class="error ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-800'"></p>
            </x-card>

            <x-card title="Verificar una etiqueta">
                <p class="text-sm text-gray-500">Lee una etiqueta para saber a qué ubicación pertenece o si es la tarjeta de un empleado.</p>
                <x-button type="button" variant="secondary" class="mt-3 w-full" @click="read()" ::disabled="!supported || working" icon="viewfinder-circle">Leer etiqueta</x-button>
                <template x-if="readResult">
                    <dl class="mt-3 space-y-1 rounded-lg bg-gray-50 p-3 text-xs">
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Contenido</dt><dd class="break-all text-right font-mono" x-text="readResult.text ?? '(vacía)'"></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">UID</dt><dd class="font-mono" x-text="readResult.serial ?? '—'"></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Ubicación</dt><dd x-text="readResult.location?.name ?? 'No reconocida'"></dd></div>
                        <div class="flex justify-between gap-2" x-show="readResult.employee"><dt class="text-gray-500">Tarjeta de</dt><dd x-text="readResult.employee"></dd></div>
                        <p x-show="readResult.outdated" class="pt-1 text-amber-700">La URL grabada no coincide con el enlace actual: vuelve a grabar esta etiqueta.</p>
                    </dl>
                </template>
            </x-card>

            <x-card title="Consejos">
                <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li>Usa etiquetas NTAG213/215/216 (las más comunes y económicas).</li>
                    <li>Pega la etiqueta en un lugar visible y fácil de alcanzar con el teléfono.</li>
                    <li>Bloquea las etiquetas una vez verificadas para evitar manipulaciones.</li>
                    <li>No se necesita Arduino ni reloj biométrico: basta con el teléfono del empleado.</li>
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
