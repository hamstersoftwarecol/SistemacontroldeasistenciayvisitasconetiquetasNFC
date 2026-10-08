<x-app-layout>
    <x-slot name="title">Usuarios</x-slot>
    <x-slot name="header">Usuarios y permisos</x-slot>
    <x-slot name="actions">
        <x-button-link :href="route('users.create')" icon="user-plus">Nuevo usuario</x-button-link>
    </x-slot>

    <x-filter-bar>
        <x-field label="Buscar" class="col-span-2"><x-text-input type="search" name="q" :value="$filters['q']" placeholder="Nombre, correo, código o departamento" class="block w-full" /></x-field>
        <x-field label="Rol"><x-select name="rol" :options="\App\Enums\Role::options()" :selected="$filters['role']" placeholder="Todos" class="block w-full" /></x-field>
        <x-field label="Estado"><x-select name="estado" :options="['active' => 'Activos', 'inactive' => 'Desactivados']" :selected="$filters['status']" placeholder="Todos" class="block w-full" /></x-field>
    </x-filter-bar>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Nombre</th><th>Rol</th><th>Departamento</th><th>Código</th><th>Tarjeta NFC</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :user="$user" size="sm" />
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <x-badge :color="match ($user->role->value) { 'admin' => 'violet', 'supervisor' => 'sky', default => 'gray' }">{{ $user->roleLabel() }}</x-badge>
                                @if ($user->hasCustomPermissions())
                                    <x-badge color="amber" title="Permisos personalizados">Personalizado</x-badge>
                                @endif
                            </td>
                            <td>{{ $user->department ?? '—' }}<span class="block text-xs text-gray-500">{{ $user->position }}</span></td>
                            <td class="font-mono text-xs">{{ $user->employee_code ?? '—' }}</td>
                            <td>{!! $user->nfc_card_uid ? '<span class="font-mono text-xs">'.e($user->nfc_card_uid).'</span>' : '<span class="text-gray-400">—</span>' !!}</td>
                            <td>
                                @if ($user->is_active)
                                    <x-badge color="emerald">Activo</x-badge>
                                @else
                                    <x-badge color="rose">Desactivado</x-badge>
                                @endif
                            </td>
                            <td class="text-right"><a href="{{ route('users.edit', $user) }}" class="font-medium text-indigo-600 hover:text-indigo-500">Editar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="users" title="Sin usuarios" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
