<x-app-layout>
    <x-slot name="title">Asistencia de {{ $attendance->user->name }}</x-slot>
    <x-slot name="header">{{ $attendance->user->name }} · {{ $attendance->date->translatedFormat('d M Y') }}</x-slot>
    <x-slot name="actions">
        @can('attendance.manage')
            <x-button-link :href="route('attendances.edit', $attendance)" variant="secondary" icon="pencil">Corregir</x-button-link>
        @endcan
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="grid grid-cols-2 content-start gap-3 lg:col-span-1 lg:grid-cols-1">
            <x-stat label="Entrada" :value="$attendance->check_in_at->format('H:i')" icon="arrow-right-end-on-rectangle" color="emerald" :hint="$attendance->checkInLocation?->name" />
            <x-stat label="Salida (último toque)" :value="$attendance->check_out_at?->format('H:i') ?? '—'" icon="arrow-left-start-on-rectangle" color="rose" :hint="$attendance->checkOutLocation?->name" />
            <x-stat label="Tiempo trabajado" :value="$attendance->workedLabel()" icon="clock" color="sky" />
            <x-stat label="Puntualidad" :value="$attendance->is_late ? $attendance->late_minutes.' min tarde' : 'A tiempo'" icon="check-badge" :color="$attendance->is_late ? 'amber' : 'emerald'" />
            @if ($attendance->notes)
                <x-card title="Notas" class="col-span-2 lg:col-span-1">
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ $attendance->notes }}</p>
                </x-card>
            @endif
        </div>

        <x-card title="Recorrido ({{ $attendance->scans->count() }} toques)" class="lg:col-span-2">
            <ol class="relative ml-2 border-l border-gray-200">
                @foreach ($attendance->scans as $scan)
                    <li class="mb-5 ml-5 last:mb-0">
                        <span @class([
                            'absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full ring-4 ring-white',
                            'bg-emerald-500' => $scan->type === 'check_in',
                            'bg-rose-500' => $scan->type === 'check_out',
                            'bg-sky-500' => $scan->type === 'visit',
                        ])></span>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-semibold tabular-nums">{{ $scan->scanned_at->format('H:i:s') }}</span>
                            <a href="{{ route('scans.receipt', $scan) }}" class="text-gray-800 hover:text-indigo-600">{{ $scan->location->name }}</a>
                            <x-badge :color="$scan->typeColor()">{{ $scan->typeLabel() }}</x-badge>
                            <span class="text-xs text-gray-400">{{ $scan->sourceLabel() }}</span>
                        </div>
                        @if ($scan->comment)
                            <p class="mt-1 text-sm text-gray-600">“{{ $scan->comment }}”</p>
                        @endif
                        @if ($scan->photoUrl())
                            <a href="{{ $scan->photoUrl() }}" target="_blank"><img src="{{ $scan->photoUrl() }}" alt="Evidencia" class="mt-2 h-24 rounded-lg border border-gray-200 object-cover"></a>
                        @endif
                    </li>
                @endforeach
            </ol>
        </x-card>
    </div>
</x-app-layout>
