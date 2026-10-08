<x-app-layout>
    <x-slot name="title">Queja {{ $complaint->code }}</x-slot>
    <x-slot name="header">{{ $complaint->code }} · {{ $complaint->subject }}</x-slot>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge :color="$complaint->statusColor()">{{ $complaint->statusLabel() }}</x-badge>
                    <x-badge :color="$complaint->priorityColor()">Prioridad {{ strtolower($complaint->priorityLabel()) }}</x-badge>
                    <x-badge>{{ $complaint->categoryLabel() }}</x-badge>
                </div>
                <h1 class="mt-3 text-lg font-semibold">{{ $complaint->subject }}</h1>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $complaint->description }}</p>
                @if ($complaint->attachment_path)
                    <a href="{{ route('complaints.attachment', $complaint) }}" target="_blank" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                        <x-heroicon-o-paper-clip class="h-4 w-4" /> Ver adjunto
                    </a>
                @endif
            </x-card>

            <x-card title="Seguimiento">
                <ol class="space-y-4">
                    <li class="flex gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500"><x-heroicon-o-flag class="h-4 w-4" /></span>
                        <div class="text-sm">
                            <p><span class="font-medium">{{ $complaint->reporterName() }}</span> creó la queja</p>
                            <p class="text-xs text-gray-500">{{ $complaint->created_at->translatedFormat('d M Y H:i') }}</p>
                        </div>
                    </li>
                    @foreach ($updates as $update)
                        <li class="flex gap-3">
                            <span @class(['flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold', 'bg-amber-100 text-amber-700' => $update->is_internal, 'bg-indigo-100 text-indigo-700' => ! $update->is_internal])>
                                {{ $update->user?->initials() ?? '?' }}
                            </span>
                            <div class="min-w-0 flex-1 text-sm">
                                <p>
                                    <span class="font-medium">{{ $update->user?->name ?? 'Sistema' }}</span>
                                    @if ($update->status_to)
                                        cambió el estado de <x-badge>{{ \App\Models\Complaint::STATUSES[$update->status_from] ?? $update->status_from }}</x-badge> a <x-badge color="indigo">{{ \App\Models\Complaint::STATUSES[$update->status_to] ?? $update->status_to }}</x-badge>
                                    @endif
                                    @if ($update->is_internal)
                                        <x-badge color="amber">Nota interna</x-badge>
                                    @endif
                                </p>
                                @if ($update->body)
                                    <p class="mt-1 whitespace-pre-line rounded-lg bg-gray-50 p-3 text-gray-700">{{ $update->body }}</p>
                                @endif
                                <p class="mt-1 text-xs text-gray-500">{{ $update->created_at->translatedFormat('d M Y H:i') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <form method="POST" action="{{ route('complaints.reply', $complaint) }}" class="mt-6 space-y-3 border-t border-gray-100 pt-4">
                    @csrf
                    <x-field name="body">
                        <x-textarea name="body" rows="3" class="block w-full" placeholder="Escribe una respuesta…" required>{{ old('body') }}</x-textarea>
                    </x-field>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        @if ($manager)
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"> Nota interna (no la ve el reportante)
                            </label>
                        @else
                            <span></span>
                        @endif
                        <x-button icon="paper-airplane">Responder</x-button>
                    </div>
                </form>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Detalles">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Reportada por</dt><dd class="text-right font-medium">{{ $complaint->reporterName() }}</dd></div>
                    @if ($manager && ! $complaint->user_id)
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Contacto</dt><dd class="text-right">{{ $complaint->reporter_email ?? '—' }}<br>{{ $complaint->reporter_phone }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Ubicación</dt><dd class="text-right">{{ $complaint->location?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Responsable</dt><dd class="text-right">{{ $complaint->assignee?->name ?? 'Sin asignar' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Creada</dt><dd class="text-right">{{ $complaint->created_at->translatedFormat('d M Y H:i') }}</dd></div>
                    @if ($complaint->resolved_at)
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Resuelta</dt><dd class="text-right">{{ $complaint->resolved_at->translatedFormat('d M Y H:i') }}</dd></div>
                    @endif
                </dl>
            </x-card>

            @if ($manager)
                <x-card title="Gestionar">
                    <form method="POST" action="{{ route('complaints.update', $complaint) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <x-field label="Estado" name="status">
                            <x-select name="status" :options="\App\Models\Complaint::STATUSES" :selected="$complaint->status" class="block w-full" />
                        </x-field>
                        <x-field label="Prioridad" name="priority">
                            <x-select name="priority" :options="\App\Models\Complaint::PRIORITIES" :selected="$complaint->priority" class="block w-full" />
                        </x-field>
                        <x-field label="Categoría" name="category">
                            <x-select name="category" :options="\App\Models\Complaint::CATEGORIES" :selected="$complaint->category" class="block w-full" />
                        </x-field>
                        <x-field label="Asignar a" name="assigned_to">
                            <x-select name="assigned_to" :options="$staff" :selected="$complaint->assigned_to" placeholder="Sin asignar" class="block w-full" />
                        </x-field>
                        <x-field label="Comentario para el reportante (opcional)" name="note">
                            <x-textarea name="note" rows="2" class="block w-full"></x-textarea>
                        </x-field>
                        <x-button icon="check" class="w-full">Guardar cambios</x-button>
                    </form>
                </x-card>
                <form method="POST" action="{{ route('complaints.destroy', $complaint) }}" onsubmit="return confirm('¿Eliminar esta queja definitivamente?')">
                    @csrf
                    @method('DELETE')
                    <x-button variant="ghost" icon="trash" class="w-full text-rose-600">Eliminar queja</x-button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
