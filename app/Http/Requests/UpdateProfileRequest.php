<?php

namespace App\Http\Requests;

use App\Services\ImageUploader;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    /** Email, username, dan role sengaja tidak bisa diubah sendiri. */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'photo' => ImageUploader::rules(),
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama lengkap', 'phone' => 'nomor telepon', 'address' => 'alamat', 'photo' => 'foto profil'];
    }
}
