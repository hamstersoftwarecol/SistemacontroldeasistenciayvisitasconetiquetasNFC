<x-app-layout>
    <x-slot name="title">Nueva queja</x-slot>
    <x-slot name="header">Reportar una queja</x-slot>

    <div class="mx-auto max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('complaints.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-field label="Asunto *" name="subject">
                    <x-text-input id="subject" name="subject" :value="old('subject')" class="block w-full" required maxlength="150" placeholder="Resume el problema en pocas palabras" />
                </x-field>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="Categoría *" name="category">
                        <x-select id="category" name="category" :options="\App\Models\Complaint::CATEGORIES" :selected="old('category', 'general')" class="block w-full" />
                    </x-field>
                    <x-field label="Prioridad *" name="priority">
                        <x-select id="priority" name="priority" :options="\App\Models\Complaint::PRIORITIES" :selected="old('priority', 'medium')" class="block w-full" />
                    </x-field>
                    <x-field label="Ubicación" name="location_id">
                        <x-select id="location_id" name="location_id" :options="$locations" :selected="old('location_id', $selectedLocation)" placeholder="Sin ubicación" class="block w-full" />
                    </x-field>
                </div>
                <x-field label="Descripción *" name="description">
                    <x-textarea id="description" name="description" rows="6" class="block w-full" required placeholder="Describe qué pasó, cuándo y cualquier detalle útil">{{ old('description') }}</x-textarea>
                </x-field>
                <x-field label="Adjunto (foto o PDF, máx. 5 MB)" name="attachment">
                    <input id="attachment" type="file" name="attachment" accept="image/*,application/pdf" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700">
                </x-field>
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <x-button-link :href="route('complaints.index')" variant="secondary">Cancelar</x-button-link>
                    <x-button icon="paper-airplane">Enviar queja</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
