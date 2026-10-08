<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Location;
use App\Models\Setting;
use App\Notifications\ComplaintCreatedNotification;
use App\Notifications\ComplaintReceivedNotification;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Permite a visitantes (huéspedes, pacientes, clientes) reportar una queja desde una ubicación.
 */
class PublicComplaintController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    public function create(string $token): View
    {
        $location = $this->location($token);

        return view('complaints.public', [
            'location' => $location,
            'companyName' => Setting::get('company_name'),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $location = $this->location($token);

        // Campo trampa para bots.
        if (filled($request->input('website'))) {
            return back()->with('status', 'Gracias. Tu reporte fue enviado.');
        }

        $data = $request->validate([
            'reporter_name' => ['required', 'string', 'max:120'],
            'reporter_email' => ['nullable', 'email', 'max:150'],
            'reporter_phone' => ['nullable', 'string', 'max:50'],
            'category' => ['required', Rule::in(array_keys(Complaint::CATEGORIES))],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:3000'],
        ], [], [
            'reporter_name' => 'nombre',
            'reporter_email' => 'correo',
            'reporter_phone' => 'teléfono',
            'category' => 'categoría',
            'subject' => 'asunto',
            'description' => 'descripción',
        ]);

        $complaint = Complaint::create($data + [
            'location_id' => $location->id,
            'priority' => 'medium',
            'status' => 'open',
        ]);

        if (Setting::bool('notify_complaints')) {
            $this->notifier->toPermission('complaints.manage', new ComplaintCreatedNotification($complaint));
        }

        $this->notifier->toEmail($complaint->reporter_email, new ComplaintReceivedNotification($complaint));

        return back()->with('status', "¡Gracias! Registramos tu reporte con el código {$complaint->fresh()->code}.");
    }

    private function location(string $token): Location
    {
        abort_unless(Setting::bool('public_complaints'), 404);

        return Location::query()->active()->where('public_token', $token)->firstOrFail();
    }
}
