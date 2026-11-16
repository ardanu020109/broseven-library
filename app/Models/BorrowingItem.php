<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BorrowingItem extends Model
{
    use HasFactory;

    public const RETURN_NOT_RETURNED = 'not_returned';
    public const RETURN_PARTIAL = 'partially_returned';
    public const RETURN_RETURNED = 'returned';

    protected $fillable = [
        'borrowing_id',
        'book_id',
        'quantity',
        'returned_quantity',
        'return_status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
        ];
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'borrowing_item_id');
    }

    /** Jumlah eksemplar yang belum dikembalikan. */
    protected function outstandingQuantity(): Attribute
    {
        return Attribute::get(fn () => max(0, $this->quantity - $this->returned_quantity));
    }
}
