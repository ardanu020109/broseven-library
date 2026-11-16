<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class LibraryProfile extends Model
{
    protected $fillable = [
        'name',
        'tagline',
        'logo',
        'description',
        'address',
        'phone',
        'email',
        'website',
        'head_name',
        'operating_hours',
        'borrowing_rules',
        'max_books_per_loan',
        'loan_duration_days',
        'fine_per_day',
        'social_media',
    ];

    protected function casts(): array
    {
        return [
            'max_books_per_loan' => 'integer',
            'loan_duration_days' => 'integer',
            'fine_per_day' => 'decimal:2',
            'social_media' => 'array',
        ];
    }

    /** Profil aktif (satu baris). Jika belum ada, kembalikan nilai bawaan tanpa menyimpan. */
    public static function current(): self
    {
        return static::query()->first() ?? new static([
            'name' => config('app.name'),
            'max_books_per_loan' => 3,
            'loan_duration_days' => 7,
            'fine_per_day' => 2000,
        ]);
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo
            ? asset('storage/' . $this->logo)
            : asset('assets/images/default-cover.svg'));
    }
}
