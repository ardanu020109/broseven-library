<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReturnTransaction extends Model
{
    /** Tabel bernama "returns"; model diberi nama lain agar tidak bentrok dengan kata kunci PHP. */
    protected $table = 'returns';

    protected $fillable = [
        'code',
        'borrowing_id',
        'return_date',
        'processed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
        ];
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    protected function totalFine(): Attribute
    {
        return Attribute::get(fn () => (float) $this->items->sum('fine_amount'));
    }

    /** Format: KMB-20261113-AB12C */
    public static function generateCode(): string
    {
        do {
            $code = 'KMB-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }
}
