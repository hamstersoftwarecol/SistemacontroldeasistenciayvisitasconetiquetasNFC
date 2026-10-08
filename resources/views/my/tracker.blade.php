<x-app-layout>
    <x-slot name="title">Mi rastreador</x-slot>
    <x-slot name="header">Mi rastreador</x-slot>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Escáner NFC dentro de la app --}}
            <section x-data="nfcScanner({ endpoint: @js(url('/t')) })" class="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 p-5 text-white shadow-lg">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-indigo-100">{{ ucfirst(now()->translatedFormat('l d \d\e F')) }}</p>
                        <p class="text-4xl font-bold tabular-nums" x-data="{ now: '' }" x-init="const tick = () => now = new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: @js(config('app.timezone')) }); tick(); setInterval(tick, 1000)" x-text="now"></p>
                        <p class="mt-1 text-sm text-indigo-100">Turno: {{ $shiftStart }} – {{ $shiftEnd }}</p>
                    </div>
                    <div class="text-right">
                        @if ($today)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-400/20 px-3 py-1 text-xs font-semibold text-emerald-50 ring-1 ring-emerald-200/40">
                                <span class="h-2 w-2 rounded-full bg-emerald-300"></span> En turno
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">Sin entrada hoy</span>
                        @endif
                    </div>
                </div>

                <div class="mt-5 rounded-xl bg-white/10 p-4">
                    <template x-if="supported">
                        <div>
                            <button type="button" @click="toggle()" class="flex w-full items-center justify-center gap-3 rounded-xl bg-white px-4 py-4 text-base font-bold text-indigo-700 shadow transition hover:bg-indigo-50">
                                <span class="relative flex h-6 w-6 items-center justify-center">
                                    <span x-show="scanning" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-60"></span>
                                    <x-heroicon-o-signal class="relative h-6 w-6" />
                                </span>
                                <span x-text="scanning ? 'Acerca el teléfono a la etiqueta…' : '{{ $today ? 'Escanear etiqueta (visita / salida)' : 'Escanear etiqueta para marcar entrada' }}'"></span>
                            </button>
                            <p class="mt-2 text-center text-xs text-indigo-100" x-show="scanning">Toca de nuevo para detener el escáner.</p>
                        </div>
                    </template>
                    <template x-if="!supported">
                        <div class="flex items-start gap-3 text-sm">
                            <x-heroicon-o-device-phone-mobile class="h-6 w-6 shrink-0" />
                            <p>Acerca tu teléfono a la etiqueta NFC: se abrirá el enlace y tu toque quedará registrado automáticamente. <span class="text-indigo-100">(El escáner integrado funciona en Chrome para Android.)</span></p>
                        </div>
                    </template>
                    <p x-show="message" x-text="message" :class="error ? 'bg-rose-500/30' : 'bg-white/15'" class="mt-3 rounded-lg px-3 py-2 text-sm" x-cloak></p>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-white/10 p-3">
                        <p class="text-xs text-indigo-100">Entrada</p>
                        <p class="text-lg font-bold tabular-nums">{{ $today?->check_in_at->format('H:i') ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-white/10 p-3">
                        <p class="text-xs text-indigo-100">Salida</p>
                        <p class="text-lg font-bold tabular-nums">{{ $today?->check_out_at?->format('H:i') ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-white/10 p-3">
                        <p class="text-xs text-indigo-100">Toques</p>
                        <p class="text-lg font-bold tabular-nums">{{ $today?->scans_count ?? 0 }}</p>
                    </div>
                </div>
            </section>

            <x-card title="Recorrido de hoy" subtitle="Primer toque = entrada · último toque = salida">
                @if ($today && $today->scans->count())
                    <ol class="relative ml-2 border-l border-gray-200">
                        @foreach ($today->scans as $scan)
                            <li class="mb-5 ml-5 last:mb-0">
                                <span @class([
                                    'absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full ring-4 ring-white',
                                    'bg-emerald-500' => $scan->type === 'check_in',
                                    'bg-rose-500' => $scan->type === 'check_out',
                                    'bg-sky-500' => $scan->type === 'visit',
                                ])></span>
                                <a href="{{ route('scans.receipt', $scan) }}" class="group block">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold tabular-nums text-gray-900">{{ $scan->scanned_at->format('H:i') }}</span>
                                        <span class="text-gray-700 group-hover:text-indigo-600">{{ $scan->location->name }}</span>
                                        <x-badge :color="$scan->typeColor()">{{ $scan->typeLabel() }}</x-badge>
                                        @if ($scan->photo_path)
                                            <x-heroicon-o-camera class="h-4 w-4 text-gray-400" />
                                        @endif
                                    </div>
                                    @if ($scan->comment)
                                        <p class="mt-0.5 text-sm text-gray-500">{{ $scan->comment }}</p>
                                    @else
                                        <p class="mt-0.5 text-xs text-indigo-600 opacity-0 transition group-hover:opacity-100">Añadir comentario →</p>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <x-empty-state icon="signal" title="Aún no has marcado hoy" description="Tu primer toque NFC del día quedará registrado como entrada." />
                @endif
            </x-card>
        </div>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                <x-stat label="Días esta semana" :value="$weekDays" icon="calendar-days" />
                <x-stat label="Horas esta semana" :value="\App\Models\Attendance::formatMinutes($weekMinutes)" icon="clock" color="sky" />
                <x-stat label="Llegadas tarde" :value="$weekLate" icon="exclamation-triangle" color="amber" class="col-span-2 lg:col-span-1" />
            </div>

            <x-card title="Últimos toques">
                <x-slot name="headerActions">
                    <a href="{{ route('my.history') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Ver historial</a>
                </x-slot>
                <ul class="divide-y divide-gray-100">
                    @forelse ($recentScans as $scan)
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-900">{{ $scan->location->name }}</p>
                                <p class="text-xs text-gray-500">{{ $scan->scanned_at->translatedFormat('d M · H:i') }}</p>
                            </div>
                            <x-badge :color="$scan->typeColor()">{{ $scan->typeLabel() }}</x-badge>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-500">Sin registros todavía.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
