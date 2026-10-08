<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Scan;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TapController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * El teléfono abre esta URL al acercarse a la etiqueta: registra el toque automáticamente.
     */
    public function tap(Request $request, string $token): View|RedirectResponse|Response
    {
        $location = Location::query()->where('token', $token)->first();

        if (! $location || ! $location->is_active) {
            return $this->invalid($location);
        }

        // Si la petición viene de otro sitio (no de la etiqueta) se pide confirmación explícita.
        if ($request->header('Sec-Fetch-Site') === 'cross-site') {
            return view('tap.confirm', ['location' => $location]);
        }

        $result = $this->attendance->registerTap($request->user(), $location, 'nfc', $this->meta($request));

        return redirect()
            ->route('scans.receipt', $result->scan)
            ->with('tap', $result->toArray());
    }

    /**
     * Registro desde el escáner Web NFC de la app o desde la pantalla de confirmación.
     */
    public function store(Request $request, string $token): JsonResponse|RedirectResponse|Response
    {
        $data = $request->validate([
            'source' => ['nullable', 'in:nfc,web_nfc'],
            'tag_uid' => ['nullable', 'string', 'max:100'],
        ]);

        $location = Location::query()->where('token', $token)->first();

        if (! $location || ! $location->is_active) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $location ? 'Esta etiqueta está desactivada.' : 'Etiqueta no reconocida.',
                ], 404);
            }

            return $this->invalid($location);
        }

        $result = $this->attendance->registerTap(
            $request->user(),
            $location,
            $data['source'] ?? 'web_nfc',
            $this->meta($request) + ['tag_uid' => $data['tag_uid'] ?? null],
        );

        if ($request->expectsJson()) {
            return response()->json($result->toArray());
        }

        return redirect()->route('scans.receipt', $result->scan)->with('tap', $result->toArray());
    }

    public function receipt(Request $request, Scan $scan): View
    {
        $this->authorizeScan($request, $scan);

        $scan->load(['user', 'location', 'attendance.checkInLocation', 'attendance.checkOutLocation']);

        $timeline = $scan->attendance
            ? $scan->attendance->scans()->with('location')->get()
            : collect();

        return view('tap.receipt', [
            'scan' => $scan,
            'timeline' => $timeline,
            'tap' => session('tap'),
            'canComment' => $scan->user_id === $request->user()->id && $scan->scanned_at->gt(now()->subDay()),
            'verification' => self::verificationCode($scan),
        ]);
    }

    public function comment(Request $request, Scan $scan): RedirectResponse
    {
        abort_unless($scan->user_id === $request->user()->id, 403);
        abort_if($scan->scanned_at->lt(now()->subDay()), 403, 'Solo puedes comentar toques de las últimas 24 horas.');

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $scan->comment = $data['comment'] ?? null;

        if ($request->hasFile('photo')) {
            if ($scan->photo_path) {
                Storage::disk('local')->delete($scan->photo_path);
            }
            $scan->photo_path = $request->file('photo')->store('scans', 'local');
        }

        $scan->save();

        return back()->with('success', 'Comentario guardado en el comprobante.');
    }

    public function photo(Request $request, Scan $scan): StreamedResponse
    {
        $this->authorizeScan($request, $scan);
        abort_unless($scan->photo_path && Storage::disk('local')->exists($scan->photo_path), 404);

        return Storage::disk('local')->response($scan->photo_path);
    }

    /**
     * Código corto que permite verificar que un comprobante no fue alterado.
     */
    public static function verificationCode(Scan $scan): string
    {
        $payload = implode('|', [$scan->id, $scan->user_id, $scan->location_id, $scan->scanned_at->format('Y-m-d H:i:s')]);

        return strtoupper(substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 10));
    }

    private function authorizeScan(Request $request, Scan $scan): void
    {
        $user = $request->user();

        abort_unless($scan->user_id === $user->id || $user->can('attendance.view_all'), 403);
    }

    private function invalid(?Location $location): Response
    {
        return response()->view('tap.invalid', [
            'message' => $location
                ? 'Esta etiqueta está desactivada. Avisa a tu supervisor.'
                : 'Esta etiqueta no está registrada en el sistema.',
        ], 404);
    }

    /**
     * @return array{ip: string|null, user_agent: string|null}
     */
    private function meta(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
