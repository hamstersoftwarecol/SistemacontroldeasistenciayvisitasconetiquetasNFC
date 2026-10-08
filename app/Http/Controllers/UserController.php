<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q')),
            'role' => $request->string('rol')->toString() ?: null,
            'status' => $request->string('estado')->toString() ?: null,
        ];

        $users = User::query()
            ->when($filters['q'], fn (Builder $q, $term) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('employee_code', 'like', "%{$term}%")
                ->orWhere('department', 'like', "%{$term}%")))
            ->when($filters['role'], fn (Builder $q, $role) => $q->where('role', $role))
            ->when($filters['status'] === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('users.index', compact('users', 'filters'));
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['role' => Role::Employee, 'is_active' => true])]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->userData();
        $data['password'] ??= Str::password(16);

        $user = User::create($data);
        $user->forceFill(['email_verified_at' => now()])->save();

        $message = "Usuario {$user->name} creado.";

        if ($request->boolean('send_welcome')) {
            $message .= $this->sendWelcome($user) ? ' Se envió un correo para que defina su contraseña.' : ' No se pudo enviar el correo de bienvenida; revisa la configuración SMTP.';
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    public function edit(User $user): View
    {
        return view('users.form', compact('user'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->userData();

        if ($user->is($request->user())) {
            // Evita que un administrador se quite a sí mismo el acceso.
            if ($data['role'] !== Role::Admin->value && $user->isAdmin()) {
                throw ValidationException::withMessages(['role' => 'No puedes quitarte el rol de administrador a ti mismo.']);
            }
            if (! $data['is_active']) {
                throw ValidationException::withMessages(['is_active' => 'No puedes desactivar tu propia cuenta.']);
            }
        }

        $user->update($data);

        if ($request->boolean('send_welcome')) {
            $this->sendWelcome($user);
        }

        return redirect()->route('users.index')->with('success', "Usuario {$user->name} actualizado.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', "Usuario {$name} eliminado junto con su historial.");
    }

    private function sendWelcome(User $user): bool
    {
        try {
            return Password::broker()->sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
