@php
    $roleDefaults = collect(\App\Enums\Role::cases())->mapWithKeys(fn ($r) => [$r->value => $r->defaultPermissions()]);
    $currentRole = old('role', $user->role?->value ?? 'employee');
    $custom = (bool) old('custom_permissions', $user->exists && $user->hasCustomPermissions());
    $selected = old('permissions', $user->exists ? $user->effectivePermissions() : $roleDefaults[$currentRole]);
@endphp

<x-app-layout>
    <x-slot name="title">{{ $user->exists ? 'Editar usuario' : 'Nuevo usuario' }}</x-slot>
    <x-slot name="header">{{ $user->exists ? $user->name : 'Nuevo usuario' }}</x-slot>

    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}"
          x-data="{
              role: @js($currentRole),
              custom: @js($custom),
              selected: @js(array_values($selected)),
              defaults: @js($roleDefaults),
              syncDefaults() { if (!this.custom) this.selected = [...this.defaults[this.role]]; },
          }"
          x-init="$watch('role', () => syncDefaults()); $watch('custom', () => syncDefaults())"
          class="grid gap-4 lg:grid-cols-3">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="space-y-4 lg:col-span-2">
            <x-card title="Datos personales">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Nombre completo *" name="name" class="sm:col-span-2">
                        <x-text-input id="name" name="name" :value="old('name', $user->name)" class="block w-full" required />
                    </x-field>
                    <x-field label="Correo *" name="email">
                        <x-text-input id="email" type="email" name="email" :value="old('email', $user->email)" class="block w-full" required />
                    </x-field>
                    <x-field label="Teléfono" name="phone">
                        <x-text-input id="phone" name="phone" :value="old('phone', $user->phone)" class="block w-full" />
                    </x-field>
                    <x-field label="Cargo" name="position">
                        <x-text-input id="position" name="position" :value="old('position', $user->position)" class="block w-full" placeholder="Camarera de piso" />
                    </x-field>
                    <x-field label="Departamento" name="department">
                        <x-text-input id="department" name="department" :value="old('department', $user->department)" class="block w-full" placeholder="Housekeeping" />
                    </x-field>
                    <x-field label="Contraseña {{ $user->exists ? '(dejar vacío para no cambiar)' : '' }}" name="password">
                        <x-text-input id="password" type="password" name="password" class="block w-full" autocomplete="new-password" />
                    </x-field>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="send_welcome" value="1" @checked(old('send_welcome')) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $user->exists ? 'Enviar enlace para restablecer contraseña' : 'Enviar correo de bienvenida para que defina su contraseña' }}
                        </label>
                    </div>
                </div>
            </x-card>

            <x-card title="Asistencia y NFC">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Código de empleado" name="employee_code" hint="Permite marcar en el kiosco escribiendo el código.">
                        <x-text-input id="employee_code" name="employee_code" :value="old('employee_code', $user->employee_code)" class="block w-full" />
                    </x-field>
                    <x-field label="UID de tarjeta NFC" name="nfc_card_uid" hint="Para el modo kiosco. Léela con el botón o con un lector USB." x-data="{ reading: false, msg: '' }">
                        <div class="flex gap-2">
                            <x-text-input id="nfc_card_uid" name="nfc_card_uid" :value="old('nfc_card_uid', $user->nfc_card_uid)" class="block w-full font-mono" x-ref="uid" />
                            <button type="button" x-show="'NDEFReader' in window" class="shrink-0 rounded-md bg-gray-100 px-3 text-sm font-medium text-gray-700 hover:bg-gray-200"
                                    @click="reading = true; msg = 'Acerca la tarjeta…'; const r = new NDEFReader(); const c = new AbortController(); r.scan({ signal: c.signal }).then(() => { r.onreading = (e) => { $refs.uid.value = e.serialNumber; msg = 'Tarjeta leída.'; reading = false; c.abort(); }; }).catch((e) => { msg = e.message; reading = false; })"
                                    x-text="reading ? 'Leyendo…' : 'Leer'"></button>
                        </div>
                        <p x-show="msg" x-text="msg" class="text-xs text-gray-500"></p>
                    </x-field>
                    <x-field label="Inicio de turno propio" name="shift_start" hint="Vacío = horario general.">
                        <x-text-input id="shift_start" type="time" name="shift_start" :value="old('shift_start', $user->shift_start ? substr($user->shift_start, 0, 5) : null)" class="block w-full" />
                    </x-field>
                    <x-field label="Fin de turno propio" name="shift_end" hint="Si termina después de medianoche se trata como turno nocturno.">
                        <x-text-input id="shift_end" type="time" name="shift_end" :value="old('shift_end', $user->shift_end ? substr($user->shift_end, 0, 5) : null)" class="block w-full" />
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Rol y acceso">
                <x-field label="Rol" name="role">
                    <select name="role" x-model="role" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach (\App\Enums\Role::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <label class="mt-4 flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Cuenta activa
                </label>
                <x-input-error :messages="$errors->get('is_active')" class="mt-1" />
            </x-card>

            <x-card title="Permisos">
                <template x-if="role === 'admin'">
                    <p class="text-sm text-gray-600">Los administradores tienen todos los permisos.</p>
                </template>
                <div x-show="role !== 'admin'">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="custom_permissions" value="0">
                        <input type="checkbox" name="custom_permissions" value="1" x-model="custom" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Personalizar permisos de este usuario
                    </label>
                    <p class="mt-1 text-xs text-gray-500" x-show="!custom">Usa los permisos predeterminados del rol.</p>

                    <div class="mt-4 space-y-4">
                        @foreach (\App\Enums\Permission::grouped() as $group => $permissions)
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $group }}</p>
                                <div class="mt-2 space-y-1.5">
                                    @foreach ($permissions as $key => $label)
                                        <label class="flex items-start gap-2 text-sm" :class="!custom && 'opacity-60'">
                                            <input type="checkbox" name="permissions[]" value="{{ $key }}" x-model="selected" :disabled="!custom" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-card>

            <div class="flex gap-2">
                <x-button-link :href="route('users.index')" variant="secondary" class="flex-1">Cancelar</x-button-link>
                <x-button icon="check" class="flex-1">Guardar</x-button>
            </div>
        </div>
    </form>

    @if ($user->exists && ! $user->is(auth()->user()))
        <form method="POST" action="{{ route('users.destroy', $user) }}" class="mt-6" onsubmit="return confirm('Se eliminará el usuario y TODO su historial de asistencia. Considera desactivarlo en su lugar. ¿Continuar?')">
            @csrf
            @method('DELETE')
            <x-button variant="ghost" icon="trash" class="text-rose-600">Eliminar usuario</x-button>
        </form>
    @endif
</x-app-layout>
