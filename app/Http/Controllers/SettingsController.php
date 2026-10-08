<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Notifications\TestMailNotification;
use App\Services\Ai\ClaudeGateway;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    private const SECTIONS = [
        'general' => 'General',
        'horario' => 'Horario y reglas',
        'correo' => 'Correo SMTP',
        'notificaciones' => 'Notificaciones',
        'ia' => 'Asistente IA',
        'copias' => 'Copias de seguridad',
    ];

    public function edit(Request $request, ClaudeGateway $ai, GoogleDriveService $drive): View
    {
        $section = array_key_exists($request->query('seccion'), self::SECTIONS) ? $request->query('seccion') : 'general';

        $values = [];
        foreach (array_keys(config('nfc.defaults')) as $key) {
            // Los secretos nunca se envían al navegador.
            $values[$key] = Setting::isEncrypted($key) ? '' : Setting::get($key);
        }

        return view('settings.edit', [
            'sections' => self::SECTIONS,
            'section' => $section,
            'values' => $values,
            'secrets' => collect(config('nfc.encrypted'))->mapWithKeys(fn ($key) => [$key => filled(Setting::get($key))]),
            'aiConfigured' => $ai->isConfigured(),
            'aiFromEnv' => blank(Setting::get('ai_api_key')) && filled(config('services.anthropic.key')),
            'driveConnected' => $drive->isConnected(),
            'redirectUri' => $drive->redirectUri(),
            'schedulerLastRun' => Cache::get('scheduler_last_run'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $section = $request->input('section');
        abort_unless(array_key_exists($section, self::SECTIONS), 422);

        $rules = match ($section) {
            'general' => [
                'company_name' => ['required', 'string', 'max:120'],
                'public_complaints' => ['boolean'],
            ],
            'horario' => [
                'work_start' => ['required', 'date_format:H:i'],
                'work_end' => ['required', 'date_format:H:i'],
                'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
                'working_days' => ['required', 'array', 'min:1'],
                'working_days.*' => ['integer', 'between:1,7'],
                'tap_cooldown_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
                'active_window_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            ],
            'correo' => [
                'mail_enabled' => ['boolean'],
                'mail_host' => ['nullable', 'required_if:mail_enabled,true', 'string', 'max:190'],
                'mail_port' => ['nullable', 'required_if:mail_enabled,true', 'integer', 'between:1,65535'],
                'mail_username' => ['nullable', 'string', 'max:190'],
                'mail_password' => ['nullable', 'string', 'max:190'],
                'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
                'mail_from_address' => ['nullable', 'required_if:mail_enabled,true', 'email', 'max:190'],
                'mail_from_name' => ['nullable', 'string', 'max:120'],
            ],
            'notificaciones' => [
                'notify_complaints' => ['boolean'],
                'notify_late' => ['boolean'],
                'notify_daily_summary' => ['boolean'],
                'daily_summary_time' => ['required', 'date_format:H:i'],
                'notify_backup_failure' => ['boolean'],
            ],
            'ia' => [
                'ai_api_key' => ['nullable', 'string', 'max:300'],
                'ai_model' => ['required', Rule::in(array_keys(config('nfc.ai_models')))],
                'ai_effort' => ['required', Rule::in(['low', 'medium', 'high'])],
            ],
            'copias' => [
                'backup_enabled' => ['boolean'],
                'backup_time' => ['required', 'date_format:H:i'],
                'backup_retention' => ['required', 'integer', 'min:1', 'max:365'],
                'gdrive_enabled' => ['boolean'],
                'gdrive_client_id' => ['nullable', 'string', 'max:300'],
                'gdrive_client_secret' => ['nullable', 'string', 'max:300'],
                'gdrive_folder_name' => ['required', 'string', 'max:120'],
            ],
        };

        // Las casillas sin marcar no se envían: se interpretan como "no".
        foreach ($rules as $key => $rule) {
            if (in_array('boolean', $rule, true)) {
                $request->merge([$key => $request->boolean($key)]);
            }
        }

        $data = $request->validate($rules, [], [
            'company_name' => 'nombre de la empresa',
            'work_start' => 'hora de entrada',
            'work_end' => 'hora de salida',
            'grace_minutes' => 'minutos de tolerancia',
            'working_days' => 'días laborables',
            'mail_host' => 'servidor SMTP',
            'mail_port' => 'puerto',
            'mail_from_address' => 'correo remitente',
            'backup_retention' => 'copias a conservar',
        ]);

        if (isset($data['working_days'])) {
            $data['working_days'] = collect($data['working_days'])->map(fn ($d) => (int) $d)->unique()->sort()->implode(',');
        }

        // Un secreto vacío significa "no cambiar".
        foreach (config('nfc.encrypted') as $secret) {
            if (array_key_exists($secret, $data) && blank($data[$secret])) {
                unset($data[$secret]);
            }
        }

        if ($request->boolean('clear_ai_key')) {
            $data['ai_api_key'] = '';
        }

        if (($data['gdrive_client_id'] ?? null) !== null && $data['gdrive_client_id'] !== Setting::get('gdrive_client_id')) {
            // Nuevas credenciales: hay que volver a autorizar la cuenta.
            $data['gdrive_refresh_token'] = '';
            $data['gdrive_folder_id'] = '';
        }

        Setting::setMany($data);

        return redirect()->route('settings.edit', ['seccion' => $section])->with('success', 'Ajustes guardados.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email']]);

        try {
            Notification::route('mail', $data['test_email'])->notifyNow(new TestMailNotification);
        } catch (Throwable $e) {
            return back()->with('error', 'No se pudo enviar: '.$e->getMessage());
        }

        return back()->with('success', "Correo de prueba enviado a {$data['test_email']}.");
    }
}
