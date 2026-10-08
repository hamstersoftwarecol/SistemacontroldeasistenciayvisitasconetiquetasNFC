<?php

use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MyTrackerController;
use App\Http\Controllers\NfcWriterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicComplaintController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TapController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

// Formulario público de quejas (código QR / NFC de la ubicación).
Route::get('/reportar/{token}', [PublicComplaintController::class, 'create'])->name('public.complaints.create');
Route::post('/reportar/{token}', [PublicComplaintController::class, 'store'])
    ->middleware('throttle:public-complaints')
    ->name('public.complaints.store');

Route::middleware('auth')->group(function () {
    Route::get('/panel', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/panel/datos', [DashboardController::class, 'data'])->name('dashboard.data');

    // Toque NFC: la URL grabada en cada etiqueta.
    Route::get('/t/{token}', [TapController::class, 'tap'])->middleware('throttle:nfc-tap')->name('tap');
    Route::post('/t/{token}', [TapController::class, 'store'])->middleware('throttle:nfc-tap')->name('tap.store');

    // Comprobantes de tiempo y comentarios.
    Route::get('/comprobante/{scan}', [TapController::class, 'receipt'])->name('scans.receipt');
    Route::patch('/comprobante/{scan}', [TapController::class, 'comment'])->name('scans.comment');
    Route::get('/comprobante/{scan}/foto', [TapController::class, 'photo'])->name('scans.photo');

    // Mi rastreador y mi historial.
    Route::get('/mi-rastreador', [MyTrackerController::class, 'index'])->name('my.tracker');
    Route::get('/mi-historial', [MyTrackerController::class, 'history'])->name('my.history');

    // Equipo en tiempo real.
    Route::middleware('can:team.view')->group(function () {
        Route::get('/equipo', [TeamController::class, 'index'])->name('team.index');
        Route::get('/equipo/estado', [TeamController::class, 'status'])->name('team.status');
    });

    // Historial de asistencia y visitas.
    Route::middleware('can:attendance.manage')->group(function () {
        Route::get('/asistencias/nueva', [AttendanceController::class, 'create'])->name('attendances.create');
        Route::post('/asistencias', [AttendanceController::class, 'store'])->name('attendances.store');
        Route::get('/asistencias/{attendance}/editar', [AttendanceController::class, 'edit'])->name('attendances.edit');
        Route::put('/asistencias/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
        Route::delete('/asistencias/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');
    });
    Route::middleware('can:attendance.view_all')->group(function () {
        Route::get('/asistencias', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::get('/asistencias/{attendance}', [AttendanceController::class, 'show'])->name('attendances.show');
        Route::get('/visitas', [ScanController::class, 'index'])->name('scans.index');
    });

    // Ubicaciones.
    Route::middleware('can:locations.manage')->group(function () {
        Route::resource('ubicaciones', LocationController::class)
            ->parameters(['ubicaciones' => 'location'])
            ->names('locations');
        Route::post('/ubicaciones/{location}/regenerar', [LocationController::class, 'regenerate'])->name('locations.regenerate');
    });

    // Escritor de etiquetas NFC y bloqueo.
    Route::middleware('can:nfc.write')->group(function () {
        Route::get('/nfc/escritor', [NfcWriterController::class, 'index'])->name('nfc.writer');
        Route::post('/nfc/ubicaciones/{location}/escrita', [NfcWriterController::class, 'written'])->name('nfc.written');
        Route::post('/nfc/ubicaciones/{location}/bloqueada', [NfcWriterController::class, 'locked'])->name('nfc.locked');
        Route::post('/nfc/identificar', [NfcWriterController::class, 'identify'])->name('nfc.identify');
    });

    // Modo kiosco (tarjetas NFC de empleados).
    Route::middleware('can:kiosk.use')->group(function () {
        Route::get('/kiosco', [KioskController::class, 'index'])->name('kiosk.index');
        Route::post('/kiosco/registrar', [KioskController::class, 'store'])->middleware('throttle:120,1')->name('kiosk.store');
    });

    // Quejas.
    Route::get('/quejas', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('/quejas/nueva', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/quejas', [ComplaintController::class, 'store'])->name('complaints.store');
    Route::get('/quejas/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    Route::post('/quejas/{complaint}/respuestas', [ComplaintController::class, 'reply'])->name('complaints.reply');
    Route::get('/quejas/{complaint}/adjunto', [ComplaintController::class, 'attachment'])->name('complaints.attachment');
    Route::middleware('can:complaints.manage')->group(function () {
        Route::patch('/quejas/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');
        Route::delete('/quejas/{complaint}', [ComplaintController::class, 'destroy'])->name('complaints.destroy');
    });

    // Informes (imprimir y exportar).
    Route::middleware('can:reports.view')->prefix('informes')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/asistencia', [ReportController::class, 'attendance'])->name('attendance');
        Route::get('/visitas', [ReportController::class, 'visits'])->name('visits');
        Route::get('/quejas', [ReportController::class, 'complaints'])->name('complaints');
    });

    // Chat del equipo.
    Route::middleware('can:chat.use')->group(function () {
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
        Route::get('/chat/contactos', [ChatController::class, 'contacts'])->name('chat.contacts');
        Route::get('/chat/mensajes', [ChatController::class, 'messages'])->name('chat.messages');
        Route::post('/chat/mensajes', [ChatController::class, 'send'])->middleware('throttle:60,1')->name('chat.send');
    });

    // Difusiones.
    Route::middleware('can:broadcasts.send')->group(function () {
        Route::get('/difusiones/nueva', [BroadcastController::class, 'create'])->name('broadcasts.create');
        Route::post('/difusiones', [BroadcastController::class, 'store'])->name('broadcasts.store');
        Route::delete('/difusiones/{broadcast}', [BroadcastController::class, 'destroy'])->name('broadcasts.destroy');
    });
    Route::get('/difusiones', [BroadcastController::class, 'index'])->name('broadcasts.index');
    Route::get('/difusiones/{broadcast}', [BroadcastController::class, 'show'])->name('broadcasts.show');
    Route::post('/difusiones/leer-todas', [BroadcastController::class, 'readAll'])->name('broadcasts.read-all');

    // Asistente de IA.
    Route::middleware('can:ai.use')->group(function () {
        Route::get('/asistente/{conversation?}', [AiAssistantController::class, 'index'])->name('ai.index');
        Route::post('/asistente/preguntar', [AiAssistantController::class, 'ask'])->middleware('throttle:ai')->name('ai.ask');
        Route::delete('/asistente/{conversation}', [AiAssistantController::class, 'destroy'])->name('ai.destroy');
    });

    // Usuarios, roles y permisos.
    Route::middleware('can:users.manage')->group(function () {
        Route::resource('usuarios', UserController::class)
            ->parameters(['usuarios' => 'user'])
            ->except('show')
            ->names('users');
    });

    // Ajustes del sistema.
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/ajustes', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/ajustes', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/ajustes/correo-prueba', [SettingsController::class, 'testMail'])->name('settings.test-mail');
    });

    // Copias de seguridad y Google Drive.
    Route::middleware('can:backups.manage')->group(function () {
        Route::get('/copias', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/copias', [BackupController::class, 'store'])->name('backups.store');
        Route::get('/copias/{backup}/descargar', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('/copias/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
        Route::get('/copias/google/conectar', [BackupController::class, 'googleRedirect'])->name('backups.google.redirect');
        Route::get('/copias/google/callback', [BackupController::class, 'googleCallback'])->name('backups.google.callback');
        Route::post('/copias/google/desconectar', [BackupController::class, 'googleDisconnect'])->name('backups.google.disconnect');
    });

    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
