<x-app-layout>
    <x-slot name="title">Difusiones</x-slot>
    <x-slot name="header">Difusiones</x-slot>
    <x-slot name="actions">
        <form method="POST" action="{{ route('broadcasts.read-all') }}">
            @csrf
            <x-button variant="secondary" icon="check">Marcar todas como leídas</x-button>
        </form>
        @can('broadcasts.send')
            <x-button-link :href="route('broadcasts.create')" icon="megaphone">Nueva difusión</x-button-link>
        @endcan
    </x-slot>

    <div class="space-y-3">
        @forelse ($broadcasts as $broadcast)
            <a href="{{ route('broadcasts.show', $broadcast) }}" @class([
                'block rounded-2xl border bg-white p-4 shadow-sm transition hover:border-indigo-300',
                'border-indigo-200 ring-1 ring-indigo-100' => ! $broadcast->read,
                'border-gray-200' => $broadcast->read,
            ])>
                <div class="flex items-start gap-3">
                    <span @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                        'bg-rose-50 text-rose-600' => $broadcast->priority === 'urgent',
                        'bg-amber-50 text-amber-600' => $broadcast->priority === 'important',
                        'bg-indigo-50 text-indigo-600' => $broadcast->priority === 'normal',
                    ])>
                        <x-heroicon-o-megaphone class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p @class(['truncate text-gray-900', 'font-bold' => ! $broadcast->read, 'font-medium' => $broadcast->read])>{{ $broadcast->title }}</p>
                            @unless ($broadcast->read)
                                <x-badge color="indigo">Nueva</x-badge>
                            @endunless
                            @if ($broadcast->priority !== 'normal')
                                <x-badge :color="$broadcast->priority === 'urgent' ? 'rose' : 'amber'">{{ $broadcast->priorityLabel() }}</x-badge>
                            @endif
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm text-gray-600">{{ $broadcast->body }}</p>
                        <p class="mt-2 text-xs text-gray-500">{{ $broadcast->sender?->name ?? 'Administración' }} · {{ $broadcast->audienceLabel() }} · {{ $broadcast->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            </a>
        @empty
            <x-card><x-empty-state icon="megaphone" title="No hay difusiones" description="Aquí verás los avisos enviados a todo el equipo." /></x-card>
        @endforelse
    </div>
    <div class="mt-4">{{ $broadcasts->links() }}</div>
</x-app-layout>
