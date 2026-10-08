<?php

namespace App\Models;

use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'date', 'check_in_at', 'check_out_at', 'check_in_location_id', 'check_out_location_id',
    'scans_count', 'is_late', 'late_minutes', 'worked_minutes', 'notes',
])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'is_late' => 'boolean',
            'scans_count' => 'integer',
            'late_minutes' => 'integer',
            'worked_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkInLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'check_in_location_id');
    }

    public function checkOutLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'check_out_location_id');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class)->orderBy('scanned_at');
    }

    public function recalculateWorkedMinutes(): void
    {
        $this->worked_minutes = $this->check_out_at
            ? (int) max(0, $this->check_in_at->diffInMinutes($this->check_out_at))
            : 0;
    }

    public function workedLabel(): string
    {
        return self::formatMinutes($this->worked_minutes);
    }

    public static function formatMinutes(int $minutes): string
    {
        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }

    public function statusLabel(): string
    {
        return $this->is_late ? 'Tarde' : 'A tiempo';
    }
}
