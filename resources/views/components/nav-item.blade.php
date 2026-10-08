@props(['href', 'active' => false, 'icon', 'badge' => null])

<a href="{{ $href }}"
   {{ $attributes->class([
       'group flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm font-medium transition',
       'bg-indigo-50 text-indigo-700' => $active,
       'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! $active,
   ]) }}>
    <x-dynamic-component :component="'heroicon-o-'.$icon" @class(['h-5 w-5 shrink-0', 'text-indigo-600' => $active, 'text-gray-400 group-hover:text-gray-500' => ! $active]) />
    <span class="flex-1 truncate">{{ $slot }}</span>
    @if ($badge)
        <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-semibold text-white">{{ $badge }}</span>
    @endif
</a>
