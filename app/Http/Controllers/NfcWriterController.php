<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NfcWriterController extends Controller
{
    public function index(Request $request): View
    {
        $locations = Location::query()->orderBy('name')->get()->map(fn (Location $location) => $this->present($location));

        return view('nfc.writer', [
            'locations' => $locations,
            'preselected' => $request->integer('ubicacion') ?: null,
        ]);
    }

    public function written(Request $request, Location $location): JsonResponse
    {
        $data = $request->validate(['tag_uid' => ['nullable', 'string', 'max:100']]);

        $location->forceFill([
            'written_at' => now(),
            'tag_uid' => User::normalizeUid($data['tag_uid'] ?? null) ?? $location->tag_uid,
        ])->save();

        return response()->json(['ok' => true, 'location' => $this->present($location)]);
    }

    public function locked(Location $location): JsonResponse
    {
        $location->forceFill([
            'is_locked' => true,
            'locked_at' => now(),
        ])->save();

        return response()->json(['ok' => true, 'location' => $this->present($location)]);
    }

    /**
     * Identifica a qué ubicación pertenece una etiqueta leída (por URL o por UID).
     */
    public function identify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:500'],
            'uid' => ['nullable', 'string', 'max:100'],
        ]);

        $location = null;
        $token = isset($data['text']) ? Location::tokenFromText($data['text']) : null;

        if ($token) {
            $location = Location::query()->where('token', $token)->first();
        }

        if (! $location && ($uid = User::normalizeUid($data['uid'] ?? null))) {
            $location = Location::query()->where('tag_uid', $uid)->first();
        }

        $user = ($uid = User::normalizeUid($data['uid'] ?? null))
            ? User::query()->where('nfc_card_uid', $uid)->first()
            : null;

        return response()->json([
            'found' => (bool) $location,
            'location' => $location ? $this->present($location) : null,
            'outdated' => $location && $token === null,
            'employee' => $user?->name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Location $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'code' => $location->code,
            'area' => $location->area,
            'is_active' => $location->is_active,
            'is_locked' => $location->is_locked,
            'tag_uid' => $location->tag_uid,
            'written_at' => $location->written_at?->translatedFormat('d M Y H:i'),
            'locked_at' => $location->locked_at?->translatedFormat('d M Y H:i'),
            'url' => $location->tapUrl(),
            'written_url' => route('nfc.written', $location),
            'locked_url' => route('nfc.locked', $location),
        ];
    }
}
