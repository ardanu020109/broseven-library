<?php

namespace App\Models;

use App\Enums\BorrowingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'isbn',
        'title',
        'category_id',
        'author_id',
        'publisher_id',
        'shelf_id',
        'shelf_number',
        'publication_year',
        'language',
        'edition',
        'description',
        'cover',
        'pages',
        'price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'pages' => 'integer',
            'price' => 'decimal:2',
            'total_stock' => 'integer',
            'available_stock' => 'integer',
            'borrowed_stock' => 'integer',
        ];
    }

    // ---------- Relasi ----------

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class);
    }

    public function borrowingItems(): HasMany
    {
        return $this->hasMany(BorrowingItem::class);
    }

    public function stockHistories(): HasMany
    {
        return $this->hasMany(BookStockHistory::class);
    }

    // ---------- Scope ----------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('available_stock', '>', 0);
    }

    public function scopeLowStock(Builder $query, int $threshold = 2): Builder
    {
        return $query->where('available_stock', '<=', $threshold);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('isbn', 'like', "%{$term}%");
        });
    }

    // ---------- Helper ----------

    /** Apakah buku masih punya transaksi aktif (menunggu persetujuan atau sedang dipinjam)? */
    public function hasOpenTransactions(): bool
    {
        return $this->borrowingItems()
            ->whereHas('borrowing', fn(Builder $q) => $q->whereIn('status', BorrowingStatus::openValues()))
            ->exists();
    }

    protected function isAvailable(): Attribute
    {
        return Attribute::get(fn() => $this->status === self::STATUS_ACTIVE && $this->available_stock > 0);
    }

    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn() => $this->cover
            ? asset('storage/' . $this->cover)
            : asset('assets/images/default-cover.svg'));
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query
            ->withSum(['borrowingItems as borrowed_total' => function (Builder $items) {
                $items->whereHas('borrowing', fn(Builder $borrowing) => $borrowing->whereNotIn('status', [
                    BorrowingStatus::Pending->value,
                    BorrowingStatus::Rejected->value,
                    BorrowingStatus::Cancelled->value,
                ]));
            }], 'quantity')
            ->orderByDesc('borrowed_total');
    }
}
