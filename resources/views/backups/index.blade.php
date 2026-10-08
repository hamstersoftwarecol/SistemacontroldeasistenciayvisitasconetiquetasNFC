<x-app-layout>
    <x-slot name="title">Copias de seguridad</x-slot>
    <x-slot name="header">Copias de seguridad</x-slot>
    <x-slot name="actions">
        <form method="POST" action="{{ route('backups.store') }}" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <x-button icon="arrow-down-tray" ::disabled="busy"><span x-text="busy ? 'Creando copia…' : 'Crear copia ahora'">Crear copia ahora</span></x-button>
        </form>
    </x-slot>

    <div class="mb-4 grid gap-4 lg:grid-cols-2">
        <x-card title="Google Drive">
            @if ($drive['connected'])
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><x-heroicon-o-cloud-arrow-up class="h-6 w-6" /></span>
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="font-semibold text-gray-900">Conectado {{ $drive['account'] ? 'como '.$drive['account'] : '' }}</p>
                        <p class="text-gray-500">Carpeta: «{{ $drive['folder'] }}» · Subida automática: {{ $drive['enabled'] ? 'activada' : 'desactivada' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('backups.google.disconnect') }}" class="mt-4" onsubmit="return confirm('¿Desconectar Google Drive?')">
                    @csrf
                    <x-button variant="secondary" icon="link-slash">Desconectar</x-button>
                </form>
            @elseif ($drive['configured'])
                <p class="text-sm text-gray-600">Credenciales guardadas. Autoriza la cuenta de Google donde se guardarán las copias.</p>
                <x-button-link :href="route('backups.google.redirect')" class="mt-4" icon="link">Conectar con Google Drive</x-button-link>
            @else
                <p class="text-sm text-gray-600">Para subir las copias automáticamente a Google Drive:</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-600">
                    <li>En <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" class="text-indigo-600">Google Cloud Console</a> habilita la <em>Google Drive API</em>.</li>
                    <li>Crea un «ID de cliente OAuth» de tipo <em>Aplicación web</em>.</li>
                    <li>Agrega este URI de redirección autorizado:
                        <code class="mt-1 block break-all rounded bg-gray-100 px-2 py-1 text-xs">{{ $drive['redirect_uri'] }}</code>
                    </li>
                    <li>Copia el Client ID y el Client Secret en <a href="{{ route('settings.edit', ['seccion' => 'copias']) }}" class="text-indigo-600">Ajustes → Copias de seguridad</a> y vuelve aquí para conectar.</li>
                </ol>
            @endif
        </x-card>

        <x-card title="Programación">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Copia automática diaria</dt><dd>{{ $schedule['enabled'] ? 'Sí, a las '.$schedule['time'] : 'Desactivada' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Copias que se conservan</dt><dd>{{ $schedule['retention'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Última ejecución del programador</dt>
                    <dd>
                        @if ($schedule['last_run'])
                            {{ \Carbon\Carbon::parse($schedule['last_run'])->diffForHumans() }}
                        @else
                            <span class="text-amber-700">Nunca</span>
                        @endif
                    </dd>
                </div>
            </dl>
            @if (! $schedule['last_run'] || \Carbon\Carbon::parse($schedule['last_run'])->lt(now()->subMinutes(10)))
                <div class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
                    El programador de tareas no se está ejecutando. Agrega esta línea al cron del servidor:
                    <code class="mt-1 block break-all rounded bg-white/70 px-2 py-1">* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code>
                </div>
            @endif
            <x-button-link :href="route('settings.edit', ['seccion' => 'copias'])" variant="secondary" class="mt-4" icon="cog-6-tooth">Cambiar ajustes</x-button-link>
        </x-card>
    </div>

    <x-card title="Copias disponibles" :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Fecha</th><th>Archivo</th><th>Tamaño</th><th>Origen</th><th>Estado</th><th>Google Drive</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($backups as $backup)
                        <tr>
                            <td class="tabular-nums">{{ $backup->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="font-mono text-xs">{{ $backup->filename }}</td>
                            <td class="tabular-nums">{{ $backup->sizeLabel() }}</td>
                            <td>{{ $backup->trigger === 'scheduled' ? 'Automática' : 'Manual' }}</td>
                            <td>
                                @if ($backup->status === 'success')
                                    <x-badge color="emerald">Correcta</x-badge>
                                @elseif ($backup->status === 'failed')
                                    <x-badge color="rose" :title="$backup->error">Fallida</x-badge>
                                @else
                                    <x-badge color="amber">En curso</x-badge>
                                @endif
                            </td>
                            <td>
                                @switch($backup->drive_status)
                                    @case('uploaded') <x-badge color="emerald">Subida</x-badge> @break
                                    @case('failed') <x-badge color="rose" :title="$backup->error">Error</x-badge> @break
                                    @default <span class="text-xs text-gray-400">No enviada</span>
                                @endswitch
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    @if ($backup->status === 'success')
                                        <a href="{{ route('backups.download', $backup) }}" class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100" title="Descargar"><x-heroicon-o-arrow-down-tray class="h-4 w-4" /></a>
                                    @endif
                                    <form method="POST" action="{{ route('backups.destroy', $backup) }}" onsubmit="return confirm('¿Eliminar esta copia (también de Google Drive)?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-md p-1.5 text-gray-500 hover:bg-rose-50 hover:text-rose-600" title="Eliminar"><x-heroicon-o-trash class="h-4 w-4" /></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @if ($backup->error)
                            <tr><td colspan="7" class="whitespace-normal text-xs text-rose-700">{{ $backup->error }}</td></tr>
                        @endif
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="cloud-arrow-up" title="Sin copias todavía" description="Crea la primera copia o espera a la copia automática diaria." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    <div class="mt-4">{{ $backups->links() }}</div>

    <x-card title="Restaurar una copia" class="mt-4">
        <p class="text-sm text-gray-600">Cada ZIP contiene <code>database.sqlite</code> (o <code>database.sql</code>) y la carpeta <code>archivos/</code> con fotos y adjuntos. Para restaurar: detén la app, reemplaza <code>database/database.sqlite</code> por el archivo de la copia y copia <code>archivos/*</code> en <code>storage/app/private/</code>.</p>
    </x-card>
</x-app-layout>
