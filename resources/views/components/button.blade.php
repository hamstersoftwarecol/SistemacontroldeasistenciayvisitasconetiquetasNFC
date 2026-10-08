@props(['variant' => 'primary', 'icon' => null])

@php
    $classes = match ($variant) {
        'secondary' => 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-500',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-500',
        'ghost' => 'text-gray-600 hover:bg-gray-100 shadow-none',
        default => 'bg-indigo-600 text-white hover:bg-indigo-500',
    };
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => "inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold shadow-sm transition disabled:cursor-not-allowed disabled:opacity-50 {$classes}"]) }}>
    @if ($icon)
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" />
    @endif
    {{ $slot }}
</button>
