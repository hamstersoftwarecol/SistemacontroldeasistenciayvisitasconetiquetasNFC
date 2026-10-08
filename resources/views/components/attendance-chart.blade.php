@props(['days'])

@php
    $max = max(1, collect($days)->max('present'));
@endphp

{{-- Barras apiladas: a tiempo (serie 1) + tarde (serie 2). Colores validados para daltonismo. --}}
<div class="viz-root" style="--series-1: #2a78d6; --series-2: #eb6834;">
    <div class="mb-3 flex items-center gap-4 text-xs text-gray-600">
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: var(--series-1)"></span>A tiempo</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: var(--series-2)"></span>Tarde</span>
    </div>

    <div class="flex h-44 items-end gap-2 border-b border-gray-200 sm:gap-3" role="img" aria-label="Asistencia de los últimos {{ count($days) }} días">
        @foreach ($days as $day)
            @php
                $onTime = $day['present'] - $day['late'];
                $total = $day['present'];
            @endphp
            <div class="group relative flex h-full flex-1 flex-col items-center justify-end" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @focusin="tip = true" @focusout="tip = false" tabindex="0">
                <span class="mb-1 text-xs font-medium tabular-nums text-gray-700">{{ $total ?: '' }}</span>
                <div class="flex w-full max-w-10 flex-col justify-end gap-[2px]" style="height: {{ $total ? max(4, round($total / $max * 100)) : 0 }}%">
                    @if ($day['late'])
                        <div class="w-full rounded-t" style="background: var(--series-2); flex: {{ $day['late'] }} 1 0%"></div>
                    @endif
                    @if ($onTime)
                        <div @class(['w-full', 'rounded-t' => ! $day['late']]) style="background: var(--series-1); flex: {{ $onTime }} 1 0%"></div>
                    @endif
                </div>
                <div x-show="tip" x-cloak class="pointer-events-none absolute bottom-full z-10 mb-2 w-max rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg">
                    <p class="font-semibold">{{ $day['date'] }}</p>
                    <p>{{ $onTime }} a tiempo · {{ $day['late'] }} tarde</p>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-1.5 flex gap-2 sm:gap-3">
        @foreach ($days as $day)
            <span @class(['flex-1 text-center text-xs', 'text-gray-600' => $day['working'], 'text-gray-400' => ! $day['working']])>{{ $day['label'] }}</span>
        @endforeach
    </div>

    <table class="sr-only">
        <caption>Asistencia por día</caption>
        <thead><tr><th>Día</th><th>A tiempo</th><th>Tarde</th></tr></thead>
        <tbody>
            @foreach ($days as $day)
                <tr><td>{{ $day['date'] }}</td><td>{{ $day['present'] - $day['late'] }}</td><td>{{ $day['late'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
