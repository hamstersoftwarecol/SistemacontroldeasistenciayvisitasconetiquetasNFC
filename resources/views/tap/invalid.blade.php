<x-app-layout>
    <x-slot name="title">Etiqueta no válida</x-slot>
    <x-slot name="header">Etiqueta no válida</x-slot>

    <div class="mx-auto max-w-md">
        <x-card>
            <div class="py-4 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                    <x-heroicon-o-no-symbol class="h-7 w-7" />
                </span>
                <h1 class="mt-4 text-lg font-semibold">No se pudo registrar</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $message }}</p>
                <x-button-link :href="route('my.tracker')" class="mt-6" icon="signal">Ir a mi rastreador</x-button-link>
            </div>
        </x-card>
    </div>
</x-app-layout>
