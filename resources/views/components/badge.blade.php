@props(['color' => 'gray'])

@php
    $classes = match ($color) {
        'emerald', 'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'rose', 'red' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'amber', 'yellow' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'sky', 'blue' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        default => 'bg-gray-50 text-gray-600 ring-gray-500/20',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>{{ $slot }}</span>
