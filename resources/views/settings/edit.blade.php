@php
    $checkbox = fn (string $name, string $label, ?string $hint = null) => compact('name', 'label', 'hint');
@endphp

<x-app-layout>
    <x-slot name="title">Ajustes</x-slot>
    <x-slot name="header">Ajustes del sistema</x-slot>

    <div class="grid gap-4 lg:grid-cols-4">
        <nav class="flex gap-1 overflow-x-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-sm lg:flex-col lg:self-start">
            @foreach ($sections as $key => $label)
                <a href="{{ route('settings.edit', ['seccion' => $key]) }}" @class(['whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium', 'bg-indigo-50 text-indigo-700' => $section === $key, 'text-gray-600 hover:bg-gray-100' => $section !== $key])>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="space-y-4 lg:col-span-3">
            <x-card :title="$sections[$section]">
                <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="{{ $section }}">

                    @switch($section)
                        @case('general')
                            <x-field label="Nombre de la empresa" name="company_name">
                                <x-text-input id="company_name" name="company_name" :value="old('company_name', $values['company_name'])" class="block w-full" required />
                            </x-field>
                            @include('settings.partials.checkbox', $checkbox('public_complaints', 'Permitir quejas públicas', 'Cada ubicación tiene un formulario público (sin iniciar sesión) para huéspedes, pacientes o clientes.'))
                            @break

                        @case('horario')
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-field label="Hora de entrada" name="work_start"><x-text-input type="time" name="work_start" :value="old('work_start', $values['work_start'])" class="block w-full" required /></x-field>
                                <x-field label="Hora de salida" name="work_end"><x-text-input type="time" name="work_end" :value="old('work_end', $values['work_end'])" class="block w-full" required /></x-field>
                                <x-field label="Tolerancia (min)" name="grace_minutes" hint="Minutos después de la entrada antes de marcar «tarde»."><x-text-input type="number" min="0" name="grace_minutes" :value="old('grace_minutes', $values['grace_minutes'])" class="block w-full" required /></x-field>
                            </div>
                            <x-field label="Días laborables" name="working_days">
                                @php $days = old('working_days', explode(',', (string) $values['working_days'])); @endphp
                                <div class="flex flex-wrap gap-2">
                                    @foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $n => $label)
                                        <label class="cursor-pointer">
                                            <input type="checkbox" name="working_days[]" value="{{ $n }}" @checked(in_array((string) $n, array_map('strval', $days), true)) class="peer sr-only">
                                            <span class="inline-block rounded-lg border border-gray-300 px-3 py-1.5 text-sm peer-checked:border-indigo-600 peer-checked:bg-indigo-600 peer-checked:text-white">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </x-field>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="Ignorar toques repetidos (segundos)" name="tap_cooldown_seconds" hint="Evita registros dobles si el teléfono lee la etiqueta dos veces.">
                                    <x-text-input type="number" min="0" name="tap_cooldown_seconds" :value="old('tap_cooldown_seconds', $values['tap_cooldown_seconds'])" class="block w-full" required />
                                </x-field>
                                <x-field label="Ventana «en turno» (minutos)" name="active_window_minutes" hint="Tiempo desde el último toque para considerar a alguien activo en el panel en vivo.">
                                    <x-text-input type="number" min="5" name="active_window_minutes" :value="old('active_window_minutes', $values['active_window_minutes'])" class="block w-full" required />
                                </x-field>
                            </div>
                            <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">Regla de asistencia: el <strong>primer toque</strong> del día es la entrada y el <strong>último toque</strong> es la salida. Los toques intermedios se guardan como visitas. Zona horaria del servidor: <strong>{{ config('app.timezone') }}</strong> (variable APP_TIMEZONE).</p>
                            @break

                        @case('correo')
                            @include('settings.partials.checkbox', $checkbox('mail_enabled', 'Enviar correos con este servidor SMTP', 'Si está desactivado se usa la configuración MAIL_* del archivo .env.'))
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-field label="Servidor SMTP" name="mail_host" class="sm:col-span-2"><x-text-input name="mail_host" :value="old('mail_host', $values['mail_host'])" placeholder="smtp.gmail.com" class="block w-full" /></x-field>
                                <x-field label="Puerto" name="mail_port"><x-text-input type="number" name="mail_port" :value="old('mail_port', $values['mail_port'])" class="block w-full" /></x-field>
                                <x-field label="Usuario" name="mail_username"><x-text-input name="mail_username" :value="old('mail_username', $values['mail_username'])" class="block w-full" autocomplete="off" /></x-field>
                                <x-field label="Contraseña" name="mail_password" :hint="$secrets['mail_password'] ? 'Guardada. Déjala vacía para no cambiarla.' : null"><x-text-input type="password" name="mail_password" class="block w-full" autocomplete="new-password" /></x-field>
                                <x-field label="Seguridad" name="mail_encryption"><x-select name="mail_encryption" :options="['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Ninguna']" :selected="old('mail_encryption', $values['mail_encryption'])" class="block w-full" /></x-field>
                                <x-field label="Correo remitente" name="mail_from_address"><x-text-input type="email" name="mail_from_address" :value="old('mail_from_address', $values['mail_from_address'])" class="block w-full" /></x-field>
                                <x-field label="Nombre remitente" name="mail_from_name" class="sm:col-span-2"><x-text-input name="mail_from_name" :value="old('mail_from_name', $values['mail_from_name'])" class="block w-full" /></x-field>
                            </div>
                            <p class="text-xs text-gray-500">Gmail: usa una «contraseña de aplicación» (requiere verificación en dos pasos), servidor smtp.gmail.com, puerto 587, TLS.</p>
                            @break

                        @case('notificaciones')
                            @include('settings.partials.checkbox', $checkbox('notify_complaints', 'Nueva queja → correo a quienes gestionan quejas'))
                            @include('settings.partials.checkbox', $checkbox('notify_late', 'Llegada tarde → correo a supervisores'))
                            @include('settings.partials.checkbox', $checkbox('notify_backup_failure', 'Fallo de copia de seguridad → correo a administradores'))
                            <div class="flex flex-wrap items-end gap-4">
                                @include('settings.partials.checkbox', $checkbox('notify_daily_summary', 'Resumen diario de asistencia por correo'))
                                <x-field label="Hora del resumen" name="daily_summary_time"><x-text-input type="time" name="daily_summary_time" :value="old('daily_summary_time', $values['daily_summary_time'])" class="block" required /></x-field>
                            </div>
                            <p class="text-xs text-gray-500">Los cambios de estado de una queja siempre se notifican a quien la reportó. Las difusiones se envían por correo cuando se marca la opción al crearlas.</p>
                            @break

                        @case('ia')
                            <div class="rounded-lg bg-indigo-50 p-3 text-sm text-indigo-900">
                                El asistente usa <strong>Claude</strong> (Anthropic) y consulta la base de datos solo con herramientas de lectura, respetando los permisos de cada usuario.
                                Obtén una clave en <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener" class="underline">console.anthropic.com</a>.
                            </div>
                            <x-field label="Clave de API de Anthropic" name="ai_api_key" :hint="$aiFromEnv ? 'Se está usando ANTHROPIC_API_KEY del archivo .env.' : ($secrets['ai_api_key'] ? 'Guardada (cifrada). Déjala vacía para no cambiarla.' : 'Empieza por sk-ant-…')">
                                <x-text-input type="password" name="ai_api_key" class="block w-full font-mono" autocomplete="off" placeholder="sk-ant-..." />
                            </x-field>
                            @if ($secrets['ai_api_key'])
                                <label class="flex items-center gap-2 text-sm text-rose-700"><input type="checkbox" name="clear_ai_key" value="1" class="rounded border-gray-300 text-rose-600 focus:ring-rose-500"> Borrar la clave guardada</label>
                            @endif
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="Modelo" name="ai_model"><x-select name="ai_model" :options="config('nfc.ai_models')" :selected="old('ai_model', $values['ai_model'])" class="block w-full" /></x-field>
                                <x-field label="Nivel de esfuerzo" name="ai_effort" hint="Más esfuerzo = respuestas más elaboradas, pero más lentas y costosas.">
                                    <x-select name="ai_effort" :options="['low' => 'Bajo (rápido)', 'medium' => 'Medio (recomendado)', 'high' => 'Alto']" :selected="old('ai_effort', $values['ai_effort'])" class="block w-full" />
                                </x-field>
                            </div>
                            <p class="text-sm">Estado: @if ($aiConfigured) <x-badge color="emerald">Configurado</x-badge> @else <x-badge color="amber">Sin clave</x-badge> @endif</p>
                            @break

                        @case('copias')
                            @include('settings.partials.checkbox', $checkbox('backup_enabled', 'Copia de seguridad automática diaria'))
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="Hora de la copia" name="backup_time"><x-text-input type="time" name="backup_time" :value="old('backup_time', $values['backup_time'])" class="block w-full" required /></x-field>
                                <x-field label="Copias a conservar" name="backup_retention"><x-text-input type="number" min="1" name="backup_retention" :value="old('backup_retention', $values['backup_retention'])" class="block w-full" required /></x-field>
                            </div>
                            <div class="border-t border-gray-100 pt-4">
                                <p class="font-medium text-gray-900">Google Drive</p>
                                <p class="mt-1 text-sm text-gray-500">Crea un ID de cliente OAuth (Aplicación web) en Google Cloud Console con la Drive API habilitada y este URI de redirección:</p>
                                <code class="mt-1 block break-all rounded bg-gray-100 px-2 py-1 text-xs">{{ $redirectUri }}</code>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="Client ID" name="gdrive_client_id" class="sm:col-span-2"><x-text-input name="gdrive_client_id" :value="old('gdrive_client_id', $values['gdrive_client_id'])" class="block w-full font-mono text-xs" /></x-field>
                                <x-field label="Client Secret" name="gdrive_client_secret" :hint="$secrets['gdrive_client_secret'] ? 'Guardado. Déjalo vacío para no cambiarlo.' : null"><x-text-input type="password" name="gdrive_client_secret" class="block w-full font-mono text-xs" autocomplete="off" /></x-field>
                                <x-field label="Carpeta en Drive" name="gdrive_folder_name"><x-text-input name="gdrive_folder_name" :value="old('gdrive_folder_name', $values['gdrive_folder_name'])" class="block w-full" required /></x-field>
                            </div>
                            @include('settings.partials.checkbox', $checkbox('gdrive_enabled', 'Subir cada copia a Google Drive'))
                            <p class="text-sm">Estado: @if ($driveConnected) <x-badge color="emerald">Conectado</x-badge> @else <x-badge color="amber">Sin conectar</x-badge> — guarda y luego usa «Conectar con Google Drive» en <a href="{{ route('backups.index') }}" class="text-indigo-600">Copias de seguridad</a>. @endif</p>
                            @break
                    @endswitch

                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <x-button icon="check">Guardar ajustes</x-button>
                    </div>
                </form>
            </x-card>

            @if ($section === 'correo')
                <x-card title="Enviar correo de prueba">
                    <form method="POST" action="{{ route('settings.test-mail') }}" class="flex flex-col gap-2 sm:flex-row">
                        @csrf
                        <x-text-input type="email" name="test_email" :value="auth()->user()->email" class="block w-full" required />
                        <x-button variant="secondary" icon="paper-airplane" class="shrink-0">Enviar prueba</x-button>
                    </form>
                    <x-input-error :messages="$errors->get('test_email')" class="mt-1" />
                </x-card>
            @endif

            <x-card title="Programador de tareas">
                <p class="text-sm text-gray-600">Las copias automáticas, los correos en cola y el resumen diario necesitan el cron de Laravel:</p>
                <code class="mt-2 block break-all rounded bg-gray-100 px-2 py-1 text-xs">* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code>
                <p class="mt-2 text-sm">Última ejecución:
                    @if ($schedulerLastRun)
                        <strong>{{ \Carbon\Carbon::parse($schedulerLastRun)->diffForHumans() }}</strong>
                    @else
                        <x-badge color="amber">nunca</x-badge>
                    @endif
                </p>
            </x-card>
        </div>
    </div>
</x-app-layout>
