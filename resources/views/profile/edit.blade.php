<x-app-layout>
    <x-slot name="title">Mi perfil</x-slot>
    <x-slot name="header">{{ __('Profile') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-4">
        <x-card>
            <div class="flex items-center gap-4">
                <x-avatar :user="$user" size="lg" />
                <div>
                    <p class="text-lg font-semibold">{{ $user->name }}</p>
                    <p class="text-sm text-gray-500">{{ $user->roleLabel() }}{{ $user->position ? ' · '.$user->position : '' }}{{ $user->department ? ' · '.$user->department : '' }}</p>
                    @if ($user->employee_code)
                        <p class="text-xs text-gray-500">Código de empleado: <span class="font-mono">{{ $user->employee_code }}</span></p>
                    @endif
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </x-card>

        <x-card>
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </x-card>
    </div>
</x-app-layout>
