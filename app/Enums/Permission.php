<?php

namespace App\Enums;

/**
 * Catálogo de permisos asignables por usuario.
 */
final class Permission
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        return [
            'Asistencia' => [
                'team.view' => 'Ver panel del equipo en tiempo real',
                'attendance.view_all' => 'Ver asistencia y visitas de todo el personal',
                'attendance.manage' => 'Crear, editar y eliminar registros de asistencia',
                'kiosk.use' => 'Usar el modo kiosco (tarjetas NFC de empleados)',
            ],
            'Ubicaciones y NFC' => [
                'locations.manage' => 'Gestionar ubicaciones',
                'nfc.write' => 'Escribir y bloquear etiquetas NFC',
            ],
            'Comunicación' => [
                'chat.use' => 'Usar el chat del equipo',
                'broadcasts.send' => 'Enviar difusiones a todo el equipo',
                'complaints.manage' => 'Gestionar quejas (asignar, responder, cerrar)',
            ],
            'Informes e IA' => [
                'reports.view' => 'Ver, imprimir y exportar informes',
                'ai.use' => 'Usar el asistente de IA',
                'ai.all_data' => 'El asistente de IA puede consultar datos de todo el personal',
            ],
            'Administración' => [
                'users.manage' => 'Gestionar usuarios, roles y permisos',
                'settings.manage' => 'Configurar el sistema (horario, SMTP, IA, Google Drive)',
                'backups.manage' => 'Gestionar copias de seguridad',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::grouped()));
    }

    public static function exists(string $permission): bool
    {
        return array_key_exists($permission, self::all());
    }
}
