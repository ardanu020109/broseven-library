<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookStockHistory extends Model
{
    public const TYPE_INITIAL = 'initial';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_BORROW = 'borrow';
    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'book_id',
        'user_id',
        'type',
        'quantity_change',
        'total_after',
        'available_after',
        'borrowed_after',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'total_after' => 'integer',
            'available_after' => 'integer',
            'borrowed_after' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
