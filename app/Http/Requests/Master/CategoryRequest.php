<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));

        $this->merge([
            'name' => $name,
            // Slug diisi otomatis dari nama bila dikosongkan.
            'slug' => Str::slug((string) ($this->input('slug') ?: $name)),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($id)],
            'slug' => ['required', 'string', 'max:120', Rule::unique('categories', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama kategori', 'slug' => 'slug', 'description' => 'deskripsi'];
    }

    public function messages(): array
    {
        return ['slug.unique' => 'Slug sudah dipakai kategori lain. Ubah nama atau isi slug secara manual.'];
    }
}
