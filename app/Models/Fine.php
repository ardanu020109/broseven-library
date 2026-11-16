<?php

namespace App\Models;

use App\Enums\FineStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fine extends Model
{
    protected $fillable = [
        'return_item_id',
        'member_id',
        'amount',
        'paid_amount',
        'status',
        'settled_at',
        'settled_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => FineStatus::class,
            'settled_at' => 'datetime',
        ];
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ReturnItem::class, 'return_item_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinePayment::class);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', FineStatus::Unpaid->value);
    }

    protected function remainingAmount(): Attribute
    {
        return Attribute::get(fn () => max(0, (float) $this->amount - (float) $this->paid_amount));
    }
}
