<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublisherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))) ?: null,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('publisher')?->id;

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('publishers', 'name')->ignore($id)],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama penerbit', 'address' => 'alamat', 'phone' => 'nomor telepon', 'email' => 'email'];
    }
}
