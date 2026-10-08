@props(['user', 'size' => 'md'])

@php
    $sizes = match ($size) {
        'sm' => 'h-8 w-8 text-xs',
        'lg' => 'h-12 w-12 text-base',
        default => 'h-10 w-10 text-sm',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700 {$sizes}"]) }}>{{ $user->initials() }}</span>
