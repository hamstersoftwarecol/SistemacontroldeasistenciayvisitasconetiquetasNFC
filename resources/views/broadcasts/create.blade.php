<x-app-layout>
    <x-slot name="title">Nueva difusión</x-slot>
    <x-slot name="header">Nueva difusión</x-slot>

    <div class="mx-auto max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('broadcasts.store') }}" class="space-y-4">
                @csrf
                <x-field label="Título *" name="title">
                    <x-text-input id="title" name="title" :value="old('title')" class="block w-full" required maxlength="150" />
                </x-field>
                <x-field label="Mensaje *" name="body">
                    <x-textarea id="body" name="body" rows="6" class="block w-full" required>{{ old('body') }}</x-textarea>
                </x-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Destinatarios" name="audience">
                        <x-select id="audience" name="audience" :options="\App\Models\Broadcast::AUDIENCES" :selected="old('audience', 'all')" class="block w-full" />
                    </x-field>
                    <x-field label="Prioridad" name="priority" hint="Importante y urgente se muestran como aviso destacado.">
                        <x-select id="priority" name="priority" :options="\App\Models\Broadcast::PRIORITIES" :selected="old('priority', 'normal')" class="block w-full" />
                    </x-field>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="send_email" value="1" @checked(old('send_email')) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Enviar también por correo electrónico (SMTP)
                </label>
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <x-button-link :href="route('broadcasts.index')" variant="secondary">Cancelar</x-button-link>
                    <x-button icon="megaphone">Enviar difusión</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
