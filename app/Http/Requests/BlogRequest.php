<?php

namespace App\Http\Requests;

use App\Models\Blog;
use App\Services\ImageUploader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-library') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'slug' => $slug !== '' ? Str::slug($slug) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', Rule::unique('blogs', 'slug')->ignore($this->route('blog')?->id)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'content' => ['required', 'string', 'max:100000'],
            'category_name' => ['nullable', 'string', 'max:100'],
            'thumbnail' => ImageUploader::rules(),
            'remove_thumbnail' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in([Blog::STATUS_DRAFT, Blog::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul',
            'slug' => 'slug',
            'excerpt' => 'ringkasan',
            'content' => 'isi artikel',
            'category_name' => 'kategori',
            'thumbnail' => 'thumbnail',
            'status' => 'status',
            'published_at' => 'tanggal publikasi',
        ];
    }

    public function messages(): array
    {
        return ['slug.unique' => 'Slug sudah dipakai artikel lain (termasuk artikel yang sudah dihapus).'];
    }
}
