<x-guest-layout>
    <x-slot name="title">Reportar un problema</x-slot>

    <div class="text-center">
        <h1 class="text-xl font-bold text-gray-900">Reportar un problema</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $companyName }} · {{ $location->name }}</p>
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center text-sm text-emerald-800">
            <x-heroicon-o-check-circle class="mx-auto mb-2 h-8 w-8" />
            {{ session('status') }}
        </div>
    @else
        <form method="POST" action="{{ route('public.complaints.store', $location->public_token) }}" class="mt-6 space-y-4">
            @csrf
            <div class="hidden" aria-hidden="true">
                <label>No llenar <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <x-field label="Tu nombre *" name="reporter_name">
                <x-text-input id="reporter_name" name="reporter_name" :value="old('reporter_name')" class="block w-full" required />
            </x-field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Correo (para avisarte)" name="reporter_email">
                    <x-text-input id="reporter_email" type="email" name="reporter_email" :value="old('reporter_email')" class="block w-full" />
                </x-field>
                <x-field label="Teléfono" name="reporter_phone">
                    <x-text-input id="reporter_phone" name="reporter_phone" :value="old('reporter_phone')" class="block w-full" />
                </x-field>
            </div>
            <x-field label="Tipo de problema *" name="category">
                <x-select id="category" name="category" :options="\App\Models\Complaint::CATEGORIES" :selected="old('category', 'general')" class="block w-full" />
            </x-field>
            <x-field label="Asunto *" name="subject">
                <x-text-input id="subject" name="subject" :value="old('subject')" class="block w-full" required maxlength="150" />
            </x-field>
            <x-field label="Descripción *" name="description">
                <x-textarea id="description" name="description" rows="5" class="block w-full" required>{{ old('description') }}</x-textarea>
            </x-field>
            <x-button class="w-full py-3" icon="paper-airplane">Enviar reporte</x-button>
        </form>
    @endif
</x-guest-layout>
