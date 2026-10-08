<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'custom_permissions' => $this->boolean('custom_permissions'),
            'nfc_card_uid' => User::normalizeUid($this->input('nfc_card_uid')),
            'employee_code' => $this->filled('employee_code') ? trim((string) $this->input('employee_code')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required_without:send_welcome', 'nullable', Password::min(8)],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['boolean'],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_code')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'nfc_card_uid' => ['nullable', 'string', 'max:100', Rule::unique('users', 'nfc_card_uid')->ignore($user)],
            'shift_start' => ['nullable', 'date_format:H:i', 'required_with:shift_end'],
            'shift_end' => ['nullable', 'date_format:H:i', 'required_with:shift_start'],
            'custom_permissions' => ['boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(Permission::all()))],
            'send_welcome' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'password' => 'contraseña',
            'role' => 'rol',
            'employee_code' => 'código de empleado',
            'phone' => 'teléfono',
            'position' => 'cargo',
            'department' => 'departamento',
            'nfc_card_uid' => 'UID de tarjeta NFC',
            'shift_start' => 'inicio de turno',
            'shift_end' => 'fin de turno',
        ];
    }

    public function messages(): array
    {
        return [
            'password.required_without' => 'Escribe una contraseña o marca «Enviar correo de bienvenida».',
        ];
    }

    /**
     * Datos listos para guardar en el modelo.
     *
     * @return array<string, mixed>
     */
    public function userData(): array
    {
        $data = $this->safe()->except(['password', 'custom_permissions', 'permissions', 'send_welcome']);

        $data['permissions'] = $this->boolean('custom_permissions') && $this->input('role') !== Role::Admin->value
            ? array_values($this->input('permissions', []))
            : null;

        if ($this->filled('password')) {
            $data['password'] = $this->input('password');
        }

        return $data;
    }
}
