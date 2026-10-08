<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('locations.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? strtoupper(trim((string) $this->input('code'))) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('locations', 'code')->ignore($this->route('location'))],
            'area' => ['nullable', 'string', 'max:120'],
            'floor' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'code' => 'código',
            'area' => 'área',
            'floor' => 'piso',
            'description' => 'descripción',
        ];
    }
}
