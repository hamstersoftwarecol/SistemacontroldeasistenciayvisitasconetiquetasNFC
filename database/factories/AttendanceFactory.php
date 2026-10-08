<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = today()->setTime(8, fake()->numberBetween(0, 30));
        $checkOut = (clone $checkIn)->addHours(8)->addMinutes(fake()->numberBetween(0, 45));

        return [
            'user_id' => User::factory(),
            'date' => $checkIn->toDateString(),
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'scans_count' => 2,
            'is_late' => $checkIn->minute > 10,
            'late_minutes' => $checkIn->minute > 10 ? $checkIn->minute : 0,
            'worked_minutes' => (int) $checkIn->diffInMinutes($checkOut),
        ];
    }
}
