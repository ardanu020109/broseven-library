<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnItem extends Model
{
    protected $fillable = [
        'return_id',
        'borrowing_item_id',
        'quantity',
        'due_date',
        'days_late',
        'fine_per_day',
        'fine_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'due_date' => 'date',
            'days_late' => 'integer',
            'fine_per_day' => 'decimal:2',
            'fine_amount' => 'decimal:2',
        ];
    }

    public function returnTransaction(): BelongsTo
    {
        return $this->belongsTo(ReturnTransaction::class, 'return_id');
    }

    public function borrowingItem(): BelongsTo
    {
        return $this->belongsTo(BorrowingItem::class, 'borrowing_item_id');
    }

    public function fine(): HasOne
    {
        return $this->hasOne(Fine::class, 'return_item_id');
    }
}
