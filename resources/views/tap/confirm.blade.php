<x-app-layout>
    <x-slot name="title">Confirmar registro</x-slot>
    <x-slot name="header">Confirmar registro</x-slot>

    <div class="mx-auto max-w-md">
        <x-card>
            <div class="text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                    <x-heroicon-o-map-pin class="h-7 w-7" />
                </span>
                <h1 class="mt-4 text-lg font-semibold">¿Registrar tu toque en {{ $location->name }}?</h1>
                <p class="mt-1 text-sm text-gray-500">Por seguridad confirma el registro, ya que el enlace no se abrió directamente desde la etiqueta NFC.</p>
            </div>
            <form method="POST" action="{{ route('tap.store', $location->token) }}" class="mt-6">
                @csrf
                <input type="hidden" name="source" value="nfc">
                <x-button class="w-full py-3 text-base" icon="check-circle">Registrar ahora</x-button>
            </form>
            <a href="{{ route('dashboard') }}" class="mt-3 block text-center text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
        </x-card>
    </div>
</x-app-layout>
