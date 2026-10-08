<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocationRequest;
use App\Models\Location;
use App\Models\Scan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $locations = Location::query()
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('area', 'like', "%{$search}%")))
            ->withCount(['scans as scans_today' => fn ($q) => $q->whereDate('scanned_at', today())])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('locations.index', compact('locations', 'search'));
    }

    public function create(): View
    {
        return view('locations.form', ['location' => new Location(['is_active' => true])]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $location = Location::create($request->validated());

        return redirect()->route('locations.show', $location)->with('success', 'Ubicación creada. Ahora graba su etiqueta NFC.');
    }

    public function show(Location $location): View
    {
        $recent = Scan::query()
            ->where('location_id', $location->id)
            ->with('user')
            ->latest('scanned_at')
            ->limit(15)
            ->get();

        return view('locations.show', [
            'location' => $location,
            'recent' => $recent,
            'stats' => [
                'today' => $location->scans()->whereDate('scanned_at', today())->count(),
                'week' => $location->scans()->whereDate('scanned_at', '>=', today()->startOfWeek())->count(),
                'total' => $location->scans()->count(),
                'complaints' => $location->complaints()->open()->count(),
            ],
        ]);
    }

    public function edit(Location $location): View
    {
        return view('locations.form', compact('location'));
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] ??= $location->code;

        $location->update($data);

        return redirect()->route('locations.show', $location)->with('success', 'Ubicación actualizada.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        return redirect()->route('locations.index')->with('success', 'Ubicación eliminada.');
    }

    /**
     * Genera un token nuevo: las etiquetas grabadas antes dejan de funcionar.
     */
    public function regenerate(Location $location): RedirectResponse
    {
        $location->forceFill([
            'token' => Location::newToken(),
            'tag_uid' => null,
            'written_at' => null,
            'is_locked' => false,
            'locked_at' => null,
        ])->save();

        return back()->with('warning', 'Se generó un nuevo enlace. Debes grabar de nuevo la etiqueta NFC de esta ubicación.');
    }
}
