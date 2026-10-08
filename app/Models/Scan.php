<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'location_id', 'attendance_id', 'scanned_at', 'type', 'source',
    'comment', 'photo_path', 'tag_uid', 'ip_address', 'user_agent',
])]
class Scan extends Model
{
    public const TYPE_CHECK_IN = 'check_in';

    public const TYPE_VISIT = 'visit';

    public const TYPE_CHECK_OUT = 'check_out';

    public const SOURCES = [
        'nfc' => 'Etiqueta NFC',
        'web_nfc' => 'Escáner en la app',
        'kiosk' => 'Kiosco',
        'manual' => 'Manual',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CHECK_IN => 'Entrada',
            self::TYPE_CHECK_OUT => 'Salida',
            default => 'Visita',
        };
    }

    public function typeColor(): string
    {
        return match ($this->type) {
            self::TYPE_CHECK_IN => 'emerald',
            self::TYPE_CHECK_OUT => 'rose',
            default => 'sky',
        };
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('scans.photo', $this) : null;
    }
}
