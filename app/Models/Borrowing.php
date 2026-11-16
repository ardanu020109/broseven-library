<?php

namespace App\Models;

use App\Enums\BorrowingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Borrowing extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'member_id',
        'requested_at',
        'approved_at',
        'borrow_date',
        'due_date',
        'processed_by',
        'status',
        'notes',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'borrow_date' => 'date',
            'due_date' => 'date',
            'status' => BorrowingStatus::class,
        ];
    }

    // ---------- Relasi ----------

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BorrowingItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnTransaction::class, 'borrowing_id');
    }

    // ---------- Scope ----------

    /** Transaksi yang belum selesai. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', BorrowingStatus::openValues());
    }

    public function scopeForMember(Builder $query, int $userId): Builder
    {
        return $query->where('member_id', $userId);
    }

    // ---------- Accessor ----------

    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn () => $this->status !== null
            && $this->status->holdsStock()
            && $this->due_date !== null
            && $this->due_date->lt(today()));
    }

    /** Sisa hari sampai jatuh tempo (negatif jika sudah lewat). */
    protected function daysRemaining(): Attribute
    {
        return Attribute::get(fn () => $this->due_date === null
            ? null
            : (int) today()->diffInDays($this->due_date, false));
    }

    // ---------- Helper ----------

    /** Format: PJM-20261113-AB12C */
    public static function generateCode(): string
    {
        do {
            $code = 'PJM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }
}
