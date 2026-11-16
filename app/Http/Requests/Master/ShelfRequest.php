<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShelfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('shelf')?->id;

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('shelves', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'kode rak', 'name' => 'nama rak', 'location' => 'lokasi', 'description' => 'keterangan'];
    }
}
