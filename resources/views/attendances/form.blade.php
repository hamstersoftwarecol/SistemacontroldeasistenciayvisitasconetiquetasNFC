<x-app-layout>
    <x-slot name="title">{{ $attendance->exists ? 'Corregir asistencia' : 'Registro manual' }}</x-slot>
    <x-slot name="header">{{ $attendance->exists ? 'Corregir asistencia' : 'Registro manual de asistencia' }}</x-slot>

    <div class="mx-auto max-w-2xl">
        <x-card>
            <form method="POST" action="{{ $attendance->exists ? route('attendances.update', $attendance) : route('attendances.store') }}" class="space-y-4">
                @csrf
                @if ($attendance->exists)
                    @method('PUT')
                    <div class="rounded-lg bg-gray-50 p-3 text-sm">
                        <p class="font-medium">{{ $attendance->user->name }}</p>
                        <p class="text-gray-500">{{ ucfirst($attendance->date->translatedFormat('l d \d\e F \d\e Y')) }}</p>
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Empleado *" name="user_id">
                            <x-select id="user_id" name="user_id" :options="$users" :selected="old('user_id')" placeholder="Selecciona…" class="block w-full" required />
                        </x-field>
                        <x-field label="Fecha *" name="date">
                            <x-text-input id="date" type="date" name="date" :value="old('date', today()->toDateString())" :max="today()->toDateString()" class="block w-full" required />
                        </x-field>
                    </div>
                    <x-field label="Ubicación *" name="location_id">
                        <x-select id="location_id" name="location_id" :options="$locations" :selected="old('location_id')" placeholder="Selecciona…" class="block w-full" required />
                    </x-field>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Hora de entrada *" name="check_in">
                        <x-text-input id="check_in" type="time" name="check_in" :value="old('check_in', $attendance->check_in_at?->format('H:i'))" class="block w-full" required />
                    </x-field>
                    <x-field label="Hora de salida" name="check_out">
                        <x-text-input id="check_out" type="time" name="check_out" :value="old('check_out', $attendance->check_out_at?->format('H:i'))" class="block w-full" />
                    </x-field>
                </div>

                <x-field label="Motivo / notas" name="notes" hint="Queda registrado para auditoría (por ejemplo: olvidó marcar la salida).">
                    <x-textarea id="notes" name="notes" rows="3" class="block w-full">{{ old('notes', $attendance->notes) }}</x-textarea>
                </x-field>

                <div class="flex items-center justify-between gap-2 border-t border-gray-100 pt-4">
                    @if ($attendance->exists)
                        <button type="submit" form="delete-attendance" class="text-sm font-medium text-rose-600 hover:text-rose-500">Eliminar registro</button>
                    @else
                        <span></span>
                    @endif
                    <div class="flex gap-2">
                        <x-button-link :href="$attendance->exists ? route('attendances.show', $attendance) : route('attendances.index')" variant="secondary">Cancelar</x-button-link>
                        <x-button icon="check">Guardar</x-button>
                    </div>
                </div>
            </form>
            @if ($attendance->exists)
                <form id="delete-attendance" method="POST" action="{{ route('attendances.destroy', $attendance) }}" onsubmit="return confirm('¿Eliminar este registro y sus toques?')">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </x-card>
    </div>
</x-app-layout>
