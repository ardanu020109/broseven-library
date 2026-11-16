<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-transactions') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['items' => 'buku yang dikembalikan', 'items.*' => 'jumlah', 'notes' => 'catatan'];
    }

    public function messages(): array
    {
        return ['items.required' => 'Pilih minimal satu buku yang dikembalikan.'];
    }
}
