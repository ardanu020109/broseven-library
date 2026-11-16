<?php

namespace App\Http\Requests;

use App\Services\ImageUploader;
use Illuminate\Foundation\Http\FormRequest;

class LibraryProfileRequest extends FormRequest
{
    /** Jaringan sosial yang didukung (sama dengan ikon di footer). */
    public const NETWORKS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'twitter' => 'X (Twitter)',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('manage-library') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'website' => ['nullable', 'string', 'url:http,https', 'max:255'],
            'head_name' => ['nullable', 'string', 'max:150'],
            'operating_hours' => ['nullable', 'string', 'max:1000'],
            'borrowing_rules' => ['nullable', 'string', 'max:5000'],
            'max_books_per_loan' => ['required', 'integer', 'between:1,20'],
            'loan_duration_days' => ['required', 'integer', 'between:1,90'],
            'fine_per_day' => ['required', 'numeric', 'between:0,1000000'],
            'logo' => ImageUploader::rules(),
            'remove_logo' => ['nullable', 'boolean'],
            'social' => ['nullable', 'array'],
        ];

        foreach (array_keys(self::NETWORKS) as $network) {
            $rules["social.{$network}"] = ['nullable', 'string', 'url:http,https', 'max:255'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [
            'name' => 'nama perpustakaan',
            'tagline' => 'tagline',
            'description' => 'deskripsi',
            'address' => 'alamat',
            'phone' => 'nomor telepon',
            'email' => 'email',
            'website' => 'website',
            'head_name' => 'nama kepala perpustakaan',
            'operating_hours' => 'jam operasional',
            'borrowing_rules' => 'aturan peminjaman',
            'max_books_per_loan' => 'maksimal buku per peminjaman',
            'loan_duration_days' => 'durasi peminjaman',
            'fine_per_day' => 'denda per hari',
            'logo' => 'logo',
        ];

        foreach (self::NETWORKS as $key => $label) {
            $attributes["social.{$key}"] = "tautan {$label}";
        }

        return $attributes;
    }

    public function messages(): array
    {
        return [
            'website.url' => 'Website harus diawali http:// atau https://.',
            'social.*.url' => 'Tautan harus diawali http:// atau https://.',
        ];
    }
}
