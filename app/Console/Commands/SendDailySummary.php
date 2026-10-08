<?php

namespace App\Console\Commands;

use App\Notifications\DailySummaryNotification;
use App\Services\Notifier;
use App\Services\TeamStatusService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:daily-summary')]
#[Description('Envía por correo el resumen de asistencia del día a supervisores y administradores')]
class SendDailySummary extends Command
{
    public function handle(TeamStatusService $team, Notifier $notifier): int
    {
        $board = $team->board();
        $rows = collect($board['rows']);
        $summary = $board['summary'];

        $notifier->toPermission('team.view', new DailySummaryNotification([
            'date' => today()->translatedFormat('d M Y'),
            'total' => $summary['total'],
            'present' => $summary['present'],
            'late' => $summary['late'],
            'absent' => $summary['absent'],
            'visits' => $summary['visits'],
            'open_complaints' => $summary['open_complaints'],
            'late_names' => $rows->where('is_late', true)->pluck('name')->all(),
            'absent_names' => $summary['working_day'] ? $rows->where('status', 'absent')->pluck('name')->all() : [],
        ]));

        $this->info('Resumen diario enviado.');

        return self::SUCCESS;
    }
}
