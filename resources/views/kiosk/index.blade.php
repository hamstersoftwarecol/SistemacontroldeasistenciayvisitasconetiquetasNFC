<x-app-layout>
    <x-slot name="title">Modo kiosco</x-slot>
    <x-slot name="header">Modo kiosco</x-slot>

    @if (! $location)
        <div class="mx-auto max-w-xl">
            <x-card title="Configurar kiosco" subtitle="Usa un teléfono o tablet fijo en la entrada para que los empleados marquen con su tarjeta NFC.">
                <form method="GET" class="space-y-4">
                    <x-field label="Ubicación del kiosco" name="ubicacion">
                        <x-select id="ubicacion" name="ubicacion" class="block w-full" :options="$locations->pluck('name', 'id')" placeholder="Selecciona…" required />
                    </x-field>
                    <x-button icon="play" class="w-full py-3">Iniciar kiosco</x-button>
                </form>
                <div class="mt-6 space-y-2 border-t border-gray-100 pt-4 text-sm text-gray-600">
                    <p class="font-medium text-gray-900">Formas de leer tarjetas:</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li><strong>Chrome para Android</strong> con NFC: el propio teléfono lee las tarjetas.</li>
                        <li><strong>Lector NFC USB</strong> (tipo teclado) en PC o tablet: el UID se escribe solo.</li>
                        <li><strong>Código de empleado</strong> escrito a mano como respaldo.</li>
                    </ul>
                    <p>Asigna la tarjeta de cada empleado en <a href="{{ route('users.index') }}" class="text-indigo-600">Usuarios</a> (campo «UID de tarjeta NFC»).</p>
                </div>
            </x-card>
        </div>
    @else
        <div x-data="kiosk({ storeUrl: @js(route('kiosk.store')), locationId: {{ $location->id }}, timeZone: @js(config('app.timezone')) })" @click="focusWedge()" class="mx-auto max-w-3xl">
            <div class="rounded-3xl bg-gradient-to-br from-slate-900 to-indigo-900 p-6 text-white shadow-xl sm:p-10">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm uppercase tracking-widest text-indigo-200">{{ $location->name }}</p>
                        <p class="mt-1 text-5xl font-bold tabular-nums sm:text-7xl" x-text="clock"></p>
                        <p class="mt-1 text-indigo-200 first-letter:uppercase" x-text="date"></p>
                    </div>
                    <a href="{{ route('kiosk.index') }}" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs hover:bg-white/20">Cambiar</a>
                </div>

                <div class="mt-8 flex min-h-48 flex-col items-center justify-center rounded-2xl bg-white/5 p-6 text-center ring-1 ring-white/10">
                    <template x-if="result">
                        <div>
                            <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full text-2xl font-bold"
                                  :class="result.type === 'check_in' ? 'bg-emerald-500' : 'bg-indigo-500'" x-text="result.initials"></span>
                            <p class="mt-4 text-3xl font-bold" x-text="result.user"></p>
                            <p class="mt-1 text-xl" :class="result.type === 'check_in' ? 'text-emerald-300' : 'text-indigo-200'" x-text="result.headline + ' · ' + result.time"></p>
                            <p x-show="result.type === 'check_in' && result.is_late" class="mt-2 text-amber-300" x-text="'Llegada con ' + result.late_minutes + ' minutos de retraso'"></p>
                        </div>
                    </template>
                    <template x-if="!result && error">
                        <div>
                            <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-rose-500">
                                <x-heroicon-o-x-mark class="h-10 w-10" />
                            </span>
                            <p class="mt-4 text-xl font-semibold text-rose-200" x-text="error"></p>
                        </div>
                    </template>
                    <template x-if="!result && !error">
                        <div>
                            <span class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-indigo-500/30">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-30"></span>
                                <x-heroicon-o-identification class="relative h-10 w-10" />
                            </span>
                            <p class="mt-4 text-2xl font-semibold">Acerca tu tarjeta</p>
                            <p class="mt-1 text-indigo-200">El primer toque del día registra tu entrada; el último, tu salida.</p>
                        </div>
                    </template>
                </div>

                {{-- Entrada oculta para lectores USB tipo teclado --}}
                <form @submit.prevent="submitWedge()" class="h-0 overflow-hidden">
                    <input x-ref="wedge" x-model="wedge" type="text" autocomplete="off" aria-label="Lector de tarjetas" class="opacity-0">
                </form>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <template x-if="supported">
                        <button type="button" @click.stop="scanning ? stopNfc() : startNfc()" class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-3 font-semibold"
                                :class="scanning ? 'bg-emerald-500 text-white' : 'bg-white text-indigo-800'">
                            <x-heroicon-o-signal class="h-5 w-5" />
                            <span x-text="scanning ? 'Lector NFC activo' : 'Activar lector NFC del dispositivo'"></span>
                        </button>
                    </template>
                    <form @submit.prevent="submitManual()" @click.stop class="flex flex-1 gap-2">
                        <input x-model="manual" type="text" placeholder="Código de empleado" class="min-w-0 flex-1 rounded-xl border-0 bg-white/10 text-white placeholder-indigo-200 focus:ring-2 focus:ring-white">
                        <button type="submit" class="rounded-xl bg-white/15 px-4 font-semibold hover:bg-white/25">Marcar</button>
                    </form>
                </div>
            </div>

            <x-card title="Últimos registros en este kiosco" class="mt-4">
                <ul class="divide-y divide-gray-100">
                    <template x-for="item in history" :key="item.key">
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="font-medium text-gray-900" x-text="item.user"></span>
                            <span class="text-gray-500" x-text="item.type_label + ' · ' + item.time"></span>
                        </li>
                    </template>
                </ul>
                <p x-show="!history.length" class="text-sm text-gray-500">Aún no hay registros en esta sesión.</p>
            </x-card>
        </div>
    @endif
</x-app-layout>
