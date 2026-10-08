<x-app-layout>
    <x-slot name="title">{{ $location->exists ? 'Editar ubicación' : 'Nueva ubicación' }}</x-slot>
    <x-slot name="header">{{ $location->exists ? 'Editar '.$location->name : 'Nueva ubicación' }}</x-slot>

    <div class="mx-auto max-w-2xl">
        <x-card>
            <form method="POST" action="{{ $location->exists ? route('locations.update', $location) : route('locations.store') }}" class="space-y-4">
                @csrf
                @if ($location->exists)
                    @method('PUT')
                @endif

                <x-field label="Nombre *" name="name">
                    <x-text-input id="name" name="name" class="block w-full" :value="old('name', $location->name)" required autofocus placeholder="Ej.: Habitación 101, Entrada principal, Bodega 2" />
                </x-field>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="Código" name="code" hint="Se genera si lo dejas vacío.">
                        <x-text-input id="code" name="code" class="block w-full uppercase" :value="old('code', $location->code)" placeholder="HAB-101" />
                    </x-field>
                    <x-field label="Área / edificio" name="area">
                        <x-text-input id="area" name="area" class="block w-full" :value="old('area', $location->area)" placeholder="Torre A" />
                    </x-field>
                    <x-field label="Piso" name="floor">
                        <x-text-input id="floor" name="floor" class="block w-full" :value="old('floor', $location->floor)" placeholder="1" />
                    </x-field>
                </div>

                <x-field label="Descripción / instrucciones" name="description">
                    <x-textarea id="description" name="description" rows="3" class="block w-full" placeholder="Qué debe revisar el personal al visitar esta ubicación">{{ old('description', $location->description) }}</x-textarea>
                </x-field>

                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_active', $location->is_active))>
                    Ubicación activa (si la desactivas, sus etiquetas dejan de registrar toques)
                </label>

                <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                    <x-button-link :href="$location->exists ? route('locations.show', $location) : route('locations.index')" variant="secondary">Cancelar</x-button-link>
                    <x-button icon="check">Guardar</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
