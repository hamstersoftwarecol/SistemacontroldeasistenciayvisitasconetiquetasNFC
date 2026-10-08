<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Broadcast;
use App\Models\Complaint;
use App\Models\Location;
use App\Models\Message;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(AttendanceService $attendance): void
    {
        $this->call(DatabaseSeeder::class);

        $supervisor = User::query()->firstOrCreate(['email' => 'supervisor@example.com'], [
            'name' => 'Laura Gómez',
            'password' => 'password',
            'role' => Role::Supervisor,
            'department' => 'Operaciones',
            'position' => 'Supervisora de piso',
            'employee_code' => 'SUP-001',
            'nfc_card_uid' => '04A1B2C3D4E5F6',
        ]);

        $people = [
            ['Carlos Ramírez', 'Housekeeping', 'Camarero de piso', null],
            ['María Fernanda López', 'Housekeeping', 'Camarera de piso', null],
            ['Andrés Torres', 'Mantenimiento', 'Técnico', '07:00'],
            ['Valentina Ruiz', 'Recepción', 'Recepcionista', null],
            ['Jorge Medina', 'Seguridad', 'Vigilante nocturno', '22:00'],
            ['Paula Castro', 'Housekeeping', 'Camarera de piso', null],
        ];

        $employees = collect($people)->map(function (array $p, int $i) {
            [$name, $department, $position, $shift] = $p;

            return User::query()->firstOrCreate(['email' => 'empleado'.($i + 1).'@example.com'], [
                'name' => $name,
                'password' => 'password',
                'role' => Role::Employee,
                'department' => $department,
                'position' => $position,
                'employee_code' => sprintf('EMP-%03d', $i + 1),
                'nfc_card_uid' => sprintf('04%012X', 0xA0B0C0D000 + $i),
                'shift_start' => $shift,
                'shift_end' => $shift === '22:00' ? '06:00' : ($shift ? '15:00' : null),
            ]);
        });

        foreach ([$supervisor, ...$employees] as $user) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $locations = collect([
            ['Entrada principal', 'Recepción', '1', 'ENT-01'],
            ['Habitación 101', 'Torre A', '1', 'HAB-101'],
            ['Habitación 102', 'Torre A', '1', 'HAB-102'],
            ['Habitación 201', 'Torre A', '2', 'HAB-201'],
            ['Habitación 202', 'Torre A', '2', 'HAB-202'],
            ['Lavandería', 'Servicios', '-1', 'LAV-01'],
            ['Cuarto de máquinas', 'Servicios', '-1', 'MAQ-01'],
            ['Piscina', 'Exteriores', null, 'PIS-01'],
        ])->map(fn (array $l) => Location::query()->firstOrCreate(['code' => $l[3]], [
            'name' => $l[0],
            'area' => $l[1],
            'floor' => $l[2],
            'description' => 'Ubicación de demostración.',
        ]));

        $locations->take(5)->each(fn (Location $l) => $l->forceFill(['written_at' => now()->subDays(20), 'is_locked' => true, 'locked_at' => now()->subDays(20)])->save());

        if (Attendance::query()->exists()) {
            return;
        }

        $comments = ['Habitación lista', 'Cambio de toallas', 'Ronda sin novedades', 'Revisión de aire acondicionado', null, null, null];
        $staff = collect([$supervisor, ...$employees])->reject(fn (User $u) => $u->shift_start === '22:00');

        for ($d = 13; $d >= 0; $d--) {
            $day = CarbonImmutable::today()->subDays($d);

            if ($day->isWeekend()) {
                continue;
            }

            foreach ($staff as $user) {
                if ($d > 0 && random_int(1, 12) === 1) {
                    continue; // ausencia ocasional
                }

                [$start] = $attendance->shiftFor($user);
                $time = CarbonImmutable::parse($day->toDateString().' '.$start)->subMinutes(20)->addMinutes(random_int(0, 36));
                if ($time->greaterThan(now())) {
                    continue; // aún no llega la hora de entrada de hoy
                }

                $end = $d === 0 ? min(now()->toImmutable(), $time->addHours(4)) : $time->addHours(8)->addMinutes(random_int(0, 50));

                $attendance->registerTap($user, $locations->first(), 'nfc', [], $time);

                $visits = random_int(2, 5);
                for ($v = 1; $v <= $visits; $v++) {
                    $at = $time->addMinutes((int) ($v * ($time->diffInMinutes($end) / ($visits + 1))));
                    if ($at->greaterThan(now())) {
                        break;
                    }
                    $result = $attendance->registerTap($user, $locations->random(), 'nfc', [], $at);
                    $result->scan->update(['comment' => $comments[array_rand($comments)]]);
                }

                if ($d > 0) {
                    $attendance->registerTap($user, $locations->first(), 'nfc', [], $end);
                }
            }
        }

        Complaint::create([
            'reporter_name' => 'Huésped hab. 202',
            'reporter_email' => 'huesped@example.com',
            'location_id' => $locations[4]->id,
            'category' => 'cleaning',
            'priority' => 'high',
            'subject' => 'No se cambiaron las toallas',
            'description' => 'Solicité cambio de toallas en la mañana y no se realizó.',
            'status' => 'open',
        ]);

        Complaint::create([
            'user_id' => $employees[2]->id,
            'location_id' => $locations[6]->id,
            'category' => 'maintenance',
            'priority' => 'urgent',
            'subject' => 'Fuga de agua en cuarto de máquinas',
            'description' => 'Hay una fuga en la tubería principal junto a la caldera.',
            'status' => 'in_progress',
            'assigned_to' => $supervisor->id,
        ]);

        Broadcast::create([
            'user_id' => $supervisor->id,
            'title' => 'Bienvenidos al nuevo sistema de asistencia NFC',
            'body' => "A partir de hoy marcamos la asistencia acercando el teléfono a las etiquetas NFC.\nEl primer toque del día es tu entrada y el último tu salida. ¡No olvides marcar en cada habitación que visites!",
            'audience' => 'all',
            'priority' => 'important',
        ]);

        Message::create(['sender_id' => $supervisor->id, 'recipient_id' => null, 'body' => '¡Buenos días equipo! Hoy tenemos ocupación completa en la Torre A.']);
        Message::create(['sender_id' => $employees[0]->id, 'recipient_id' => null, 'body' => 'Entendido, empezamos por el piso 2.']);
        Message::create(['sender_id' => $employees[1]->id, 'recipient_id' => $supervisor->id, 'body' => 'Laura, la habitación 102 necesita mantenimiento en el baño.']);

        $this->command?->info('Datos de demostración creados. Contraseña de todos los usuarios: password');
    }
}
