<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Supervisor => 'Supervisor',
            self::Employee => 'Empleado',
        };
    }

    /**
     * Permisos que recibe un usuario de este rol cuando no tiene permisos personalizados.
     *
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => array_keys(Permission::all()),
            self::Supervisor => [
                'team.view',
                'attendance.view_all',
                'kiosk.use',
                'complaints.manage',
                'reports.view',
                'broadcasts.send',
                'chat.use',
                'ai.use',
                'ai.all_data',
            ],
            self::Employee => [
                'chat.use',
                'ai.use',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
