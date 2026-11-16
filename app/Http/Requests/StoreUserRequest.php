<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Services\ImageUploader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /** ID pengguna yang sedang diubah (null saat membuat). Dioverride oleh UpdateUserRequest. */
    protected function currentUserId(): ?int
    {
        return null;
    }

    /** @return array<string, array<int, mixed>> */
    protected function extraRules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => Str::lower(trim((string) $this->input('username'))),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        $id = $this->currentUserId();

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash:ascii', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'role' => ['required', Rule::in(Role::values())],
            'photo' => ImageUploader::rules(),
            'remove_photo' => ['nullable', 'boolean'],
        ] + $this->extraRules();
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'username' => 'username',
            'email' => 'email',
            'phone' => 'nomor telepon',
            'address' => 'alamat',
            'role' => 'role',
            'password' => 'password',
            'photo' => 'foto profil',
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Username sudah dipakai (termasuk akun yang sudah dihapus).',
            'email.unique' => 'Email sudah dipakai (termasuk akun yang sudah dihapus).',
        ];
    }
}
