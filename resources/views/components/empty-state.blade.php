@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-4 py-10 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-6 w-6" />
    </span>
    <p class="mt-3 font-medium text-gray-900">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
