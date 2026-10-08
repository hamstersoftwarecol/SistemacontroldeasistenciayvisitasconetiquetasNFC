<x-app-layout>
    <x-slot name="title">{{ $broadcast->title }}</x-slot>
    <x-slot name="header">Difusión</x-slot>

    <div class="mx-auto max-w-2xl space-y-4">
        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge :color="match ($broadcast->priority) { 'urgent' => 'rose', 'important' => 'amber', default => 'indigo' }">{{ $broadcast->priorityLabel() }}</x-badge>
                <x-badge>{{ $broadcast->audienceLabel() }}</x-badge>
                @if ($broadcast->send_email)
                    <x-badge color="sky"><x-heroicon-o-envelope class="h-3 w-3" /> Enviada por correo</x-badge>
                @endif
            </div>
            <h1 class="mt-3 text-xl font-bold">{{ $broadcast->title }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $broadcast->sender?->name ?? 'Administración' }} · {{ $broadcast->created_at->translatedFormat('d M Y H:i') }}</p>
            <div class="mt-4 whitespace-pre-line text-gray-800">{{ $broadcast->body }}</div>
        </x-card>

        @if ($stats)
            <x-card title="Lectura">
                <p class="text-sm text-gray-700">Leída por <strong>{{ $stats['read'] }}</strong> de {{ $stats['recipients'] }} destinatarios.</p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-indigo-600" style="width: {{ $stats['recipients'] ? min(100, round($stats['read'] / $stats['recipients'] * 100)) : 0 }}%"></div>
                </div>
                <form method="POST" action="{{ route('broadcasts.destroy', $broadcast) }}" class="mt-4" onsubmit="return confirm('¿Eliminar esta difusión?')">
                    @csrf
                    @method('DELETE')
                    <x-button variant="ghost" icon="trash" class="text-rose-600">Eliminar difusión</x-button>
                </form>
            </x-card>
        @endif

        <x-button-link :href="route('broadcasts.index')" variant="secondary" icon="arrow-left">Volver a difusiones</x-button-link>
    </div>
</x-app-layout>
