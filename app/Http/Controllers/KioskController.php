<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KioskController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): View
    {
        $locations = Location::query()->active()->orderBy('name')->get();
        $location = $locations->firstWhere('id', $request->integer('ubicacion'));

        return view('kiosk.index', compact('locations', 'location'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer'],
            'uid' => ['nullable', 'string', 'max:100', 'required_without:employee_code'],
            'employee_code' => ['nullable', 'string', 'max:50'],
        ]);

        $location = Location::query()->active()->find($data['location_id']);

        if (! $location) {
            return response()->json(['message' => 'La ubicación del kiosco no está activa.'], 422);
        }

        $user = $this->findEmployee($data['uid'] ?? null, $data['employee_code'] ?? null);

        if (! $user) {
            return response()->json(['message' => 'Tarjeta o código no registrado. Pide a un administrador que la asigne.'], 404);
        }

        if (! $user->is_active) {
            return response()->json(['message' => "La cuenta de {$user->name} está desactivada."], 403);
        }

        $result = $this->attendance->registerTap($user, $location, 'kiosk', [
            'tag_uid' => $data['uid'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($result->toArray() + ['initials' => $user->initials()]);
    }

    private function findEmployee(?string $uid, ?string $code): ?User
    {
        if ($normalized = User::normalizeUid($uid)) {
            $user = User::query()->where('nfc_card_uid', $normalized)->first();

            if ($user) {
                return $user;
            }

            // Algunos lectores USB envían el código de empleado en lugar del UID.
            $code ??= $uid;
        }

        return filled($code)
            ? User::query()->where('employee_code', trim($code))->first()
            : null;
    }
}
