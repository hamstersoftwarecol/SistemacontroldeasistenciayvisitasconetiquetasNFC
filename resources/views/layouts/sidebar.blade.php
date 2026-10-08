@php
    $user = auth()->user();
    $sections = [
        'Principal' => [
            ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'home', 'label' => 'Panel', 'can' => null],
            ['route' => 'my.tracker', 'match' => 'my.tracker', 'icon' => 'signal', 'label' => 'Mi rastreador', 'can' => null],
            ['route' => 'my.history', 'match' => 'my.history', 'icon' => 'calendar-days', 'label' => 'Mi historial', 'can' => null],
        ],
        'Equipo' => [
            ['route' => 'team.index', 'match' => 'team.*', 'icon' => 'user-group', 'label' => 'Equipo en vivo', 'can' => 'team.view'],
            ['route' => 'attendances.index', 'match' => 'attendances.*', 'icon' => 'clipboard-document-check', 'label' => 'Asistencias', 'can' => 'attendance.view_all'],
            ['route' => 'scans.index', 'match' => 'scans.index', 'icon' => 'map', 'label' => 'Visitas', 'can' => 'attendance.view_all'],
            ['route' => 'kiosk.index', 'match' => 'kiosk.*', 'icon' => 'computer-desktop', 'label' => 'Modo kiosco', 'can' => 'kiosk.use'],
        ],
        'Comunicación' => [
            ['route' => 'chat.index', 'match' => 'chat.*', 'icon' => 'chat-bubble-left-right', 'label' => 'Chat del equipo', 'can' => 'chat.use', 'badge' => $unreadChat ?? 0],
            ['route' => 'broadcasts.index', 'match' => 'broadcasts.*', 'icon' => 'megaphone', 'label' => 'Difusiones', 'can' => null, 'badge' => isset($unreadBroadcasts) ? $unreadBroadcasts->count() : 0],
            ['route' => 'complaints.index', 'match' => 'complaints.*', 'icon' => 'flag', 'label' => 'Quejas', 'can' => null],
            ['route' => 'ai.index', 'match' => 'ai.*', 'icon' => 'sparkles', 'label' => 'Asistente IA', 'can' => 'ai.use'],
        ],
        'Gestión' => [
            ['route' => 'locations.index', 'match' => 'locations.*', 'icon' => 'map-pin', 'label' => 'Ubicaciones', 'can' => 'locations.manage'],
            ['route' => 'nfc.writer', 'match' => 'nfc.*', 'icon' => 'pencil-square', 'label' => 'Escritor NFC', 'can' => 'nfc.write'],
            ['route' => 'reports.index', 'match' => 'reports.*', 'icon' => 'chart-bar', 'label' => 'Informes', 'can' => 'reports.view'],
            ['route' => 'users.index', 'match' => 'users.*', 'icon' => 'users', 'label' => 'Usuarios', 'can' => 'users.manage'],
            ['route' => 'settings.edit', 'match' => 'settings.*', 'icon' => 'cog-6-tooth', 'label' => 'Ajustes', 'can' => 'settings.manage'],
            ['route' => 'backups.index', 'match' => 'backups.*', 'icon' => 'cloud-arrow-up', 'label' => 'Copias de seguridad', 'can' => 'backups.manage'],
        ],
    ];
@endphp

<div class="flex h-full flex-col">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-5 py-4">
        <x-application-logo class="h-9 w-9 text-indigo-600" />
        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-gray-900">{{ $companyName ?? config('app.name') }}</p>
            <p class="text-xs text-gray-500">Asistencia NFC</p>
        </div>
    </a>

    <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-6">
        @foreach ($sections as $title => $items)
            @php $visible = array_filter($items, fn ($item) => $item['can'] === null || $user->can($item['can'])); @endphp
            @if (count($visible))
                <div>
                    <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $title }}</p>
                    <div class="space-y-1">
                        @foreach ($visible as $item)
                            <x-nav-item :href="route($item['route'])" :active="request()->routeIs($item['match'])" :icon="$item['icon']" :badge="($item['badge'] ?? 0) ?: null">
                                {{ $item['label'] }}
                            </x-nav-item>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-gray-200 p-3">
        <div class="flex items-center gap-3 rounded-lg px-2 py-2">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">{{ $user->initials() }}</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-gray-900">{{ $user->name }}</p>
                <p class="truncate text-xs text-gray-500">{{ $user->roleLabel() }}</p>
            </div>
            <a href="{{ route('profile.edit') }}" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Mi perfil">
                <x-heroicon-o-user-circle class="h-5 w-5" />
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Cerrar sesión">
                    <x-heroicon-o-arrow-right-start-on-rectangle class="h-5 w-5" />
                </button>
            </form>
        </div>
    </div>
</div>
