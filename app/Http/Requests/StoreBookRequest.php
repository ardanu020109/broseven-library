<?php

namespace App\Http\Requests;

use App\Models\Book;
use App\Services\ImageUploader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    /** ID buku yang sedang diubah (null saat membuat). Dioverride oleh UpdateBookRequest. */
    protected function currentBookId(): ?int
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        $isbn = trim((string) $this->input('isbn'));

        $this->merge([
            'isbn' => $isbn !== '' ? $isbn : null,
            'title' => trim((string) $this->input('title')),
        ]);
    }

    public function rules(): array
    {
        $maxYear = (int) date('Y') + 1;

        return [
            'isbn' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\s]{5,20}$/', Rule::unique('books', 'isbn')->ignore($this->currentBookId())],
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'author_id' => ['required', 'integer', Rule::exists('authors', 'id')],
            'publisher_id' => ['required', 'integer', Rule::exists('publishers', 'id')],
            'shelf_id' => ['nullable', 'integer', Rule::exists('shelves', 'id')],
            'shelf_number' => ['nullable', 'string', 'max:20'],
            'publication_year' => ['nullable', 'integer', "between:1000,{$maxYear}"],
            'language' => ['required', 'string', 'max:50'],
            'edition' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'pages' => ['nullable', 'integer', 'between:1,20000'],
            'price' => ['nullable', 'numeric', 'between:0,9999999999'],
            'total_stock' => ['required', 'integer', 'between:0,100000'],
            'stock_note' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([Book::STATUS_ACTIVE, Book::STATUS_INACTIVE])],
            'cover' => ImageUploader::rules(),
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'isbn' => 'ISBN',
            'title' => 'judul buku',
            'category_id' => 'kategori',
            'author_id' => 'penulis',
            'publisher_id' => 'penerbit',
            'shelf_id' => 'rak',
            'shelf_number' => 'nomor rak',
            'publication_year' => 'tahun terbit',
            'language' => 'bahasa',
            'edition' => 'edisi',
            'description' => 'deskripsi',
            'pages' => 'jumlah halaman',
            'price' => 'harga',
            'total_stock' => 'total stok',
            'stock_note' => 'alasan perubahan stok',
            'status' => 'status',
            'cover' => 'cover',
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.unique' => 'ISBN sudah dipakai buku lain (termasuk buku yang sudah dihapus).',
            'isbn.regex' => 'ISBN hanya boleh berisi huruf, angka, dan strip (5 sampai 20 karakter).',
        ];
    }
}
