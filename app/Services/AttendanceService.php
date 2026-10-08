<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Location;
use App\Models\Scan;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LateArrivalNotification;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reglas de asistencia: el primer toque del día es la entrada y el último toque es la salida.
 */
class AttendanceService
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Registra un toque NFC de un usuario en una ubicación.
     *
     * @param  array{tag_uid?: string|null, ip?: string|null, user_agent?: string|null, comment?: string|null}  $meta
     */
    public function registerTap(User $user, Location $location, string $source = 'nfc', array $meta = [], ?CarbonInterface $at = null): TapResult
    {
        $at = CarbonImmutable::instance($at ?? now());

        $result = $this->attempt($user, $location, $source, $meta, $at);

        if ($result->isCheckIn() && ! $result->duplicate && $result->attendance->is_late && Setting::bool('notify_late')) {
            $this->notifier->toPermission('team.view', new LateArrivalNotification($result->attendance), except: $user);
        }

        return $result;
    }

    private function attempt(User $user, Location $location, string $source, array $meta, CarbonImmutable $at, bool $retry = true): TapResult
    {
        try {
            return DB::transaction(fn () => $this->write($user, $location, $source, $meta, $at));
        } catch (QueryException $e) {
            // Dos toques simultáneos pueden chocar con el índice único (user_id, date): se reintenta una vez.
            if ($retry) {
                return $this->attempt($user, $location, $source, $meta, $at, false);
            }

            throw $e;
        }
    }

    private function write(User $user, Location $location, string $source, array $meta, CarbonImmutable $at): TapResult
    {
        $cooldown = max(0, Setting::int('tap_cooldown_seconds', 60));

        if ($cooldown > 0) {
            $recent = Scan::query()
                ->where('user_id', $user->id)
                ->where('location_id', $location->id)
                ->whereBetween('scanned_at', [$at->subSeconds($cooldown), $at])
                ->latest('scanned_at')
                ->first();

            if ($recent && $recent->attendance) {
                return new TapResult($recent, $recent->attendance, duplicate: true);
            }
        }

        $workDate = $this->workDateFor($user, $at);

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $workDate)
            ->first();

        if ($attendance === null) {
            [$isLate, $lateMinutes] = $this->lateness($user, $workDate, $at);

            $attendance = Attendance::create([
                'user_id' => $user->id,
                'date' => $workDate,
                'check_in_at' => $at,
                'check_in_location_id' => $location->id,
                'scans_count' => 1,
                'is_late' => $isLate,
                'late_minutes' => $lateMinutes,
                'worked_minutes' => 0,
            ]);

            $type = Scan::TYPE_CHECK_IN;
        } else {
            // El último toque siempre es la salida: el toque anterior marcado como salida pasa a ser visita.
            Scan::query()
                ->where('attendance_id', $attendance->id)
                ->where('type', Scan::TYPE_CHECK_OUT)
                ->update(['type' => Scan::TYPE_VISIT]);

            $attendance->check_out_at = $at;
            $attendance->check_out_location_id = $location->id;
            $attendance->scans_count = $attendance->scans_count + 1;
            $attendance->recalculateWorkedMinutes();
            $attendance->save();

            $type = Scan::TYPE_CHECK_OUT;
        }

        $scan = Scan::create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'attendance_id' => $attendance->id,
            'scanned_at' => $at,
            'type' => $type,
            'source' => array_key_exists($source, Scan::SOURCES) ? $source : 'nfc',
            'comment' => isset($meta['comment']) ? Str::limit((string) $meta['comment'], 2000, '') : null,
            'tag_uid' => User::normalizeUid($meta['tag_uid'] ?? null),
            'ip_address' => $meta['ip'] ?? null,
            'user_agent' => isset($meta['user_agent']) ? Str::limit((string) $meta['user_agent'], 250, '') : null,
        ]);

        return new TapResult($scan, $attendance);
    }

    /**
     * Fecha laboral a la que pertenece un toque. Para turnos nocturnos (fin < inicio),
     * los toques de madrugada cuentan para el día anterior.
     */
    public function workDateFor(User $user, CarbonImmutable $at): string
    {
        [$start, $end] = $this->shiftFor($user);

        if ($end < $start) {
            $endToday = $at->setTimeFromTimeString($end)->addHours(4);

            if ($at->lessThan($endToday)) {
                return $at->subDay()->toDateString();
            }
        }

        return $at->toDateString();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function shiftFor(User $user): array
    {
        $start = substr((string) ($user->shift_start ?: Setting::get('work_start', '08:00')), 0, 5);
        $end = substr((string) ($user->shift_end ?: Setting::get('work_end', '17:00')), 0, 5);

        return [$start, $end];
    }

    /**
     * @return array{0: bool, 1: int}
     */
    public function lateness(User $user, string $workDate, CarbonImmutable $checkIn): array
    {
        [$start] = $this->shiftFor($user);

        $expected = CarbonImmutable::parse($workDate.' '.$start, $checkIn->getTimezone());
        $grace = max(0, Setting::int('grace_minutes', 10));

        if ($checkIn->lessThanOrEqualTo($expected->addMinutes($grace))) {
            return [false, 0];
        }

        return [true, (int) $expected->diffInMinutes($checkIn)];
    }

    /**
     * Corrige manualmente un registro y sincroniza el estado de tardanza y horas trabajadas.
     */
    public function refresh(Attendance $attendance): Attendance
    {
        [$isLate, $lateMinutes] = $this->lateness(
            $attendance->user,
            $attendance->date->toDateString(),
            CarbonImmutable::instance($attendance->check_in_at)
        );

        $attendance->is_late = $isLate;
        $attendance->late_minutes = $lateMinutes;
        $attendance->recalculateWorkedMinutes();
        $attendance->save();

        return $attendance;
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return in_array($date->isoWeekday(), Setting::workingDays(), true);
    }
}
