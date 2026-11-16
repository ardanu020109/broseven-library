<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\LibraryProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::Anggota;
    }

    public function rules(): array
    {
        $max = max(1, (int) LibraryProfile::current()->max_books_per_loan);

        return [
            'books' => ['required', 'array', 'min:1', "max:{$max}"],
            'books.*' => ['required', 'integer', 'distinct', Rule::exists('books', 'id')],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['books' => 'buku', 'books.*' => 'buku', 'notes' => 'catatan'];
    }

    public function messages(): array
    {
        $max = max(1, (int) LibraryProfile::current()->max_books_per_loan);

        return [
            'books.required' => 'Pilih minimal satu buku.',
            'books.min' => 'Pilih minimal satu buku.',
            'books.max' => "Maksimal {$max} buku dalam satu peminjaman.",
            'books.*.exists' => 'Salah satu buku yang dipilih tidak ditemukan.',
        ];
    }
}
