<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Valores por defecto de la configuración editable
    |--------------------------------------------------------------------------
    |
    | Estos valores se usan mientras el administrador no los cambie desde
    | Ajustes. Lo guardado en la tabla "settings" tiene prioridad.
    |
    */

    'defaults' => [
        // General
        'company_name' => env('APP_NAME', 'AsistenciaNFC'),
        'public_complaints' => '1',

        // Horario y reglas de asistencia
        'work_start' => '08:00',
        'work_end' => '17:00',
        'grace_minutes' => '10',
        'working_days' => '1,2,3,4,5',
        'tap_cooldown_seconds' => '60',
        'active_window_minutes' => '120',

        // Correo SMTP
        'mail_enabled' => '0',
        'mail_host' => '',
        'mail_port' => '587',
        'mail_username' => '',
        'mail_password' => '',
        'mail_encryption' => 'tls',
        'mail_from_address' => '',
        'mail_from_name' => env('APP_NAME', 'AsistenciaNFC'),

        // Notificaciones
        'notify_complaints' => '1',
        'notify_late' => '0',
        'notify_daily_summary' => '0',
        'daily_summary_time' => '18:00',
        'notify_backup_failure' => '1',

        // Asistente IA (Claude)
        'ai_api_key' => '',
        'ai_model' => env('AI_MODEL', 'claude-opus-5-5'),
        'ai_effort' => 'medium',

        // Copias de seguridad
        'backup_enabled' => '1',
        'backup_time' => '02:00',
        'backup_retention' => '14',
        'gdrive_enabled' => '0',
        'gdrive_client_id' => '',
        'gdrive_client_secret' => '',
        'gdrive_refresh_token' => '',
        'gdrive_folder_id' => '',
        'gdrive_folder_name' => 'Copias AsistenciaNFC',
        'gdrive_account' => '',

        // Estado interno
        'scheduler_last_run' => '',
    ],

    /*
    | Claves que se guardan cifradas con APP_KEY.
    */
    'encrypted' => [
        'mail_password',
        'ai_api_key',
        'gdrive_client_secret',
        'gdrive_refresh_token',
    ],

    /*
    | Modelos de Claude seleccionables en Ajustes.
    */
    'ai_models' => [
        'claude-opus-5-5' => 'Claude Opus 5.5 (recomendado)',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (más rápido y económico)',
        'claude-haiku-5-5' => 'Claude Haiku 5.5 (el más económico)',
        'claude-fable-5-1' => 'Claude Fable 5.1 (el más capaz)',
    ],

    // Modelos que aceptan el reintento automático del servidor (fallbacks: "default").
    'ai_fallback_models' => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-fable-5-1'],
];
