<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| Requiere en el servidor: * * * * * php /ruta/artisan schedule:run
|
*/

// Latido: permite mostrar en el panel si el programador está funcionando.
Schedule::call(fn () => Cache::forever('scheduler_last_run', now()->toIso8601String()))
    ->everyMinute()
    ->name('scheduler-heartbeat');

// Procesa la cola de correos sin necesidad de un worker permanente (útil en hosting compartido).
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5);

// Copia de seguridad diaria (y subida a Google Drive si está conectado).
Schedule::command('backup:run --trigger=scheduled')
    ->dailyAt(Setting::get('backup_time') ?: '02:00')
    ->when(fn () => Setting::bool('backup_enabled'))
    ->withoutOverlapping(60);

// Resumen diario de asistencia por correo.
Schedule::command('attendance:daily-summary')
    ->dailyAt(Setting::get('daily_summary_time') ?: '18:00')
    ->when(fn () => Setting::bool('notify_daily_summary'));
