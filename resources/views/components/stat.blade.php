@props(['label', 'value' => null, 'icon' => null, 'color' => 'indigo', 'hint' => null, 'bind' => null])

@php
    $iconClasses = match ($color) {
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'gray' => 'bg-gray-100 text-gray-600',
        default => 'bg-indigo-50 text-indigo-600',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-4 shadow-sm']) }}>
    <div class="flex items-center gap-3">
        @if ($icon)
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $iconClasses }}">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
            </span>
        @endif
        <div class="min-w-0">
            <p class="text-[11px] font-medium uppercase leading-tight tracking-wide text-gray-500">{{ $label }}</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900" @if ($bind) x-text="{{ $bind }}" @endif>{{ $value }}</p>
        </div>
    </div>
    @if ($hint)
        <p class="mt-2 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
