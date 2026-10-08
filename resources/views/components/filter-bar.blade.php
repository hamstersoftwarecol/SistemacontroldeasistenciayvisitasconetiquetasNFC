<form method="GET" {{ $attributes->merge(['class' => 'mb-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm print:hidden']) }}>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
        {{ $slot }}
        <div class="col-span-2 flex items-end gap-2 md:col-span-1">
            <x-button icon="funnel" class="flex-1">Filtrar</x-button>
            <a href="{{ url()->current() }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" title="Limpiar filtros">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </a>
        </div>
    </div>
    @isset($extra)
        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">{{ $extra }}</div>
    @endisset
</form>
