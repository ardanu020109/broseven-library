<?php

namespace App\Http\Requests\Master;

use App\Services\ImageUploader;
use Illuminate\Foundation\Http\FormRequest;

class AuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'biography' => ['nullable', 'string', 'max:5000'],
            'photo' => ImageUploader::rules(),
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama penulis', 'biography' => 'biografi', 'photo' => 'foto'];
    }
}
