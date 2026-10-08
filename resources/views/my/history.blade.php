<x-app-layout>
    <x-slot name="title">Mi historial</x-slot>
    <x-slot name="header">Mi historial</x-slot>
    <x-slot name="actions">
        <x-button type="button" variant="secondary" icon="printer" onclick="window.print()">Imprimir</x-button>
    </x-slot>

    <div class="mb-4 flex items-center justify-between gap-3">
        <a href="{{ route('my.history', ['mes' => $previous]) }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 print:hidden" title="Mes anterior">
            <x-heroicon-o-chevron-left class="h-5 w-5" />
        </a>
        <h2 class="text-lg font-semibold capitalize">{{ $month->translatedFormat('F Y') }}</h2>
        @if ($next)
            <a href="{{ route('my.history', ['mes' => $next]) }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 print:hidden" title="Mes siguiente">
                <x-heroicon-o-chevron-right class="h-5 w-5" />
            </a>
        @else
            <span class="w-9"></span>
        @endif
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
        <x-stat label="Días trabajados" :value="$stats['days']" icon="calendar-days" />
        <x-stat label="Horas" :value="\App\Models\Attendance::formatMinutes($stats['minutes'])" icon="clock" color="sky" />
        <x-stat label="Llegadas tarde" :value="$stats['late']" icon="exclamation-triangle" color="amber" />
        <x-stat label="Ausencias" :value="$stats['absences']" icon="x-circle" color="rose" />
        <x-stat label="Toques" :value="$stats['visits']" icon="map-pin" color="violet" class="col-span-2 md:col-span-1" />
    </div>

    <x-card :padding="false">
        @forelse ($attendances as $attendance)
            <details class="group border-b border-gray-100 last:border-0" @if ($loop->first) open @endif>
                <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 hover:bg-gray-50 sm:px-5">
                    <div class="w-14 shrink-0 text-center">
                        <p class="text-xs uppercase text-gray-500">{{ $attendance->date->translatedFormat('D') }}</p>
                        <p class="text-xl font-bold">{{ $attendance->date->format('d') }}</p>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium tabular-nums text-gray-900">
                            {{ $attendance->check_in_at->format('H:i') }} → {{ $attendance->check_out_at?->format('H:i') ?? '—' }}
                            <span class="text-gray-500">· {{ $attendance->workedLabel() }}</span>
                        </p>
                        <p class="truncate text-xs text-gray-500">{{ $attendance->scans_count }} toques · {{ $attendance->checkInLocation?->name }}</p>
                    </div>
                    @if ($attendance->is_late)
                        <x-badge color="amber">{{ $attendance->late_minutes }} min tarde</x-badge>
                    @else
                        <x-badge color="emerald">A tiempo</x-badge>
                    @endif
                    <x-heroicon-o-chevron-down class="h-4 w-4 text-gray-400 transition group-open:rotate-180 print:hidden" />
                </summary>
                <ul class="space-y-2 bg-gray-50 px-4 py-3 sm:px-5">
                    @foreach ($attendance->scans as $scan)
                        <li class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="w-12 font-semibold tabular-nums">{{ $scan->scanned_at->format('H:i') }}</span>
                            <x-badge :color="$scan->typeColor()">{{ $scan->typeLabel() }}</x-badge>
                            <a href="{{ route('scans.receipt', $scan) }}" class="text-gray-700 hover:text-indigo-600">{{ $scan->location->name }}</a>
                            @if ($scan->comment)
                                <span class="w-full pl-14 text-xs text-gray-500">“{{ $scan->comment }}”</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </details>
        @empty
            <x-empty-state icon="calendar-days" title="Sin registros este mes" />
        @endforelse
    </x-card>
</x-app-layout>
