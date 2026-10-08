<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Scan;

final readonly class TapResult
{
    public function __construct(
        public Scan $scan,
        public Attendance $attendance,
        public bool $duplicate = false,
    ) {}

    public function isCheckIn(): bool
    {
        return $this->scan->type === Scan::TYPE_CHECK_IN;
    }

    public function headline(): string
    {
        if ($this->duplicate) {
            return 'Toque ya registrado';
        }

        return match ($this->scan->type) {
            Scan::TYPE_CHECK_IN => 'Entrada registrada',
            default => 'Visita registrada',
        };
    }

    public function detail(): string
    {
        if ($this->duplicate) {
            return 'Ya habías marcado en esta ubicación hace unos segundos; no se creó un registro nuevo.';
        }

        return $this->isCheckIn()
            ? 'Primer toque del día: queda como tu hora de entrada.'
            : 'Este toque queda como tu hora de salida provisional. Si vuelves a marcar, se actualizará.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $scan = $this->scan->loadMissing('location', 'user');

        return [
            'ok' => true,
            'duplicate' => $this->duplicate,
            'type' => $scan->type,
            'type_label' => $scan->typeLabel(),
            'headline' => $this->headline(),
            'detail' => $this->detail(),
            'user' => $scan->user->name,
            'location' => $scan->location->name,
            'time' => $scan->scanned_at->format('H:i:s'),
            'date' => $scan->scanned_at->translatedFormat('l d \d\e F Y'),
            'check_in' => $this->attendance->check_in_at->format('H:i'),
            'is_late' => $this->attendance->is_late,
            'late_minutes' => $this->attendance->late_minutes,
            'receipt_url' => route('scans.receipt', $scan),
        ];
    }
}
