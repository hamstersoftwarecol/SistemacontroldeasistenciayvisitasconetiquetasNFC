<x-app-layout>
    <x-slot name="title">Comprobante #{{ $scan->id }}</x-slot>
    <x-slot name="header">Comprobante de tiempo</x-slot>
    <x-slot name="actions">
        <x-button type="button" variant="secondary" icon="printer" onclick="window.print()">Imprimir</x-button>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-4">
        @if ($tap)
            <div @class([
                'rounded-2xl p-5 text-white shadow-lg print:hidden',
                'bg-emerald-600' => ! $tap['duplicate'] && $tap['type'] === 'check_in',
                'bg-indigo-600' => ! $tap['duplicate'] && $tap['type'] !== 'check_in',
                'bg-gray-600' => $tap['duplicate'],
            ])>
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/20">
                        <x-heroicon-o-check-circle class="h-8 w-8" />
                    </span>
                    <div>
                        <p class="text-xl font-bold">{{ $tap['headline'] }}</p>
                        <p class="text-sm text-white/90">{{ $tap['detail'] }}</p>
                    </div>
                </div>
                @if ($tap['type'] === 'check_in' && $tap['is_late'] && ! $tap['duplicate'])
                    <p class="mt-3 rounded-lg bg-white/15 px-3 py-2 text-sm">Llegada registrada con {{ $tap['late_minutes'] }} minutos de retraso.</p>
                @endif
            </div>
        @endif

        <x-card>
            <div class="flex flex-col items-center text-center">
                <x-badge :color="$scan->typeColor()" class="text-sm">{{ $scan->typeLabel() }}</x-badge>
                <p class="mt-3 text-5xl font-bold tabular-nums tracking-tight text-gray-900">{{ $scan->scanned_at->format('H:i:s') }}</p>
                <p class="mt-1 text-gray-500">{{ ucfirst($scan->scanned_at->translatedFormat('l d \d\e F \d\e Y')) }}</p>
            </div>

            <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-gray-100 pt-5 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-gray-500">Empleado</dt>
                    <dd class="font-medium text-gray-900">{{ $scan->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Ubicación</dt>
                    <dd class="font-medium text-gray-900">{{ $scan->location->fullName() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Método</dt>
                    <dd class="font-medium text-gray-900">{{ $scan->sourceLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Código de verificación</dt>
                    <dd class="font-mono font-semibold tracking-wider text-gray-900">{{ $verification }}</dd>
                </div>
                @if ($scan->attendance)
                    <div>
                        <dt class="text-gray-500">Entrada del día</dt>
                        <dd class="font-medium text-gray-900">
                            {{ $scan->attendance->check_in_at->format('H:i') }}
                            @if ($scan->attendance->is_late)
                                <x-badge color="amber">{{ $scan->attendance->late_minutes }} min tarde</x-badge>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Salida (último toque)</dt>
                        <dd class="font-medium text-gray-900">{{ $scan->attendance->check_out_at?->format('H:i') ?? '—' }} · {{ $scan->attendance->workedLabel() }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        <x-card title="Comentario y evidencia">
            @if ($canComment)
                <form method="POST" action="{{ route('scans.comment', $scan) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <x-field label="Comentario" name="comment" hint="Ej.: habitación limpia, ronda sin novedades, equipo revisado…">
                        <x-textarea id="comment" name="comment" rows="3" class="block w-full" placeholder="Escribe una nota sobre esta visita">{{ old('comment', $scan->comment) }}</x-textarea>
                    </x-field>
                    <x-field label="Foto (opcional)" name="photo">
                        <input id="photo" type="file" name="photo" accept="image/*" capture="environment" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700">
                    </x-field>
                    <x-button icon="check">Guardar comentario</x-button>
                </form>
            @else
                <p class="whitespace-pre-line text-sm text-gray-700">{{ $scan->comment ?: 'Sin comentarios.' }}</p>
            @endif

            @if ($scan->photoUrl())
                <a href="{{ $scan->photoUrl() }}" target="_blank" class="mt-4 block">
                    <img src="{{ $scan->photoUrl() }}" alt="Evidencia" class="max-h-72 rounded-xl border border-gray-200 object-cover">
                </a>
            @endif
        </x-card>

        @if ($timeline->count() > 1)
            <x-card title="Recorrido del día">
                <ol class="relative ml-2 border-l border-gray-200">
                    @foreach ($timeline as $item)
                        <li class="mb-4 ml-5 last:mb-0">
                            <span @class([
                                'absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full ring-4 ring-white',
                                'bg-emerald-500' => $item->type === 'check_in',
                                'bg-rose-500' => $item->type === 'check_out',
                                'bg-sky-500' => $item->type === 'visit',
                            ])></span>
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="font-semibold tabular-nums">{{ $item->scanned_at->format('H:i') }}</span>
                                <span class="text-gray-700">{{ $item->location->name }}</span>
                                <x-badge :color="$item->typeColor()">{{ $item->typeLabel() }}</x-badge>
                                @if ($item->is($scan))
                                    <span class="text-xs text-indigo-600">(este comprobante)</span>
                                @endif
                            </div>
                            @if ($item->comment)
                                <p class="mt-0.5 text-sm text-gray-500">{{ $item->comment }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-card>
        @endif

        <div class="flex flex-wrap gap-2 print:hidden">
            <x-button-link :href="route('my.tracker')" icon="signal">Mi rastreador</x-button-link>
            <x-button-link :href="route('my.history')" variant="secondary" icon="calendar-days">Mi historial</x-button-link>
        </div>
    </div>
</x-app-layout>
