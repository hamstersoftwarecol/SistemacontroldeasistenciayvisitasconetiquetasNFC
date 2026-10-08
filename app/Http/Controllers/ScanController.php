<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->date('desde')?->toDateString() ?? today()->toDateString();
        $to = $request->date('hasta')?->toDateString() ?? today()->toDateString();
        $filters = [
            'from' => $from,
            'to' => $to < $from ? $from : $to,
            'user_id' => $request->integer('empleado') ?: null,
            'location_id' => $request->integer('ubicacion') ?: null,
            'type' => $request->string('tipo')->toString() ?: null,
            'q' => trim((string) $request->query('q')),
        ];

        $scans = Scan::query()
            ->with(['user', 'location'])
            ->whereDate('scanned_at', '>=', $filters['from'])
            ->whereDate('scanned_at', '<=', $filters['to'])
            ->when($filters['user_id'], fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filters['location_id'], fn (Builder $q, $id) => $q->where('location_id', $id))
            ->when($filters['type'], fn (Builder $q, $type) => $q->where('type', $type))
            ->when($filters['q'], fn (Builder $q, $term) => $q->where('comment', 'like', "%{$term}%"))
            ->latest('scanned_at')
            ->paginate(40)
            ->withQueryString();

        return view('scans.index', [
            'scans' => $scans,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
