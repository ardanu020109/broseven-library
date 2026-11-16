<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Book;
use App\Models\BookStockHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya tempat yang boleh mengubah kolom stok buku.
 * Invarian: total = tersedia + dipinjam, dan tidak ada nilai yang minus.
 */
class BookStockService
{
    /** Menetapkan stok awal buku yang baru dibuat. Panggil di dalam DB::transaction. */
    public function initialize(Book $book, int $total, ?User $actor = null, ?string $notes = 'Stok awal'): void
    {
        $book->forceFill([
            'total_stock' => $total,
            'available_stock' => $total,
            'borrowed_stock' => 0,
        ])->save();

        $this->log($book, BookStockHistory::TYPE_INITIAL, $total, $actor, $notes);
    }

    /**
     * Mengubah total stok. Stok tersedia mengikuti: tersedia = total - dipinjam.
     *
     * @return bool true jika ada perubahan, false jika total sama dengan sebelumnya
     *
     * @throws ValidationException jika total lebih kecil dari jumlah yang sedang dipinjam
     */
    public function adjustTotal(Book $book, int $newTotal, ?User $actor = null, ?string $notes = null): bool
    {
        return DB::transaction(function () use ($book, $newTotal, $actor, $notes) {
            // Kunci baris buku agar tidak bentrok dengan proses peminjaman/pengembalian.
            $locked = Book::query()->lockForUpdate()->findOrFail($book->getKey());

            if ($newTotal < $locked->borrowed_stock) {
                throw ValidationException::withMessages([
                    'total_stock' => "Total stok tidak boleh kurang dari jumlah yang sedang dipinjam ({$locked->borrowed_stock}).",
                ]);
            }

            $delta = $newTotal - $locked->total_stock;

            if ($delta === 0) {
                return false;
            }

            $locked->forceFill([
                'total_stock' => $newTotal,
                'available_stock' => $newTotal - $locked->borrowed_stock,
            ])->save();

            $this->log($locked, BookStockHistory::TYPE_ADJUSTMENT, $delta, $actor, $notes);

            $book->refresh();

            return true;
        });
    }

        /**
     * Mengurangi stok tersedia dan menambah stok dipinjam saat peminjaman disetujui.
     * $lockedBook WAJIB sudah dikunci (lockForUpdate) oleh pemanggil di dalam transaksi.
     *
     * @throws BusinessRuleException jika buku nonaktif atau stok tidak cukup
     */
    public function lend(Book $lockedBook, int $quantity, ?User $actor, Model $reference): void
    {
        if ($quantity < 1) {
            throw new BusinessRuleException('Jumlah peminjaman tidak valid.');
        }

        if ($lockedBook->status !== Book::STATUS_ACTIVE) {
            throw new BusinessRuleException("Buku \"{$lockedBook->title}\" sedang dinonaktifkan.");
        }

        if ($lockedBook->available_stock < $quantity) {
            throw new BusinessRuleException(
                "Stok buku \"{$lockedBook->title}\" tidak mencukupi (tersedia {$lockedBook->available_stock})."
            );
        }

        $lockedBook->forceFill([
            'available_stock' => $lockedBook->available_stock - $quantity,
            'borrowed_stock' => $lockedBook->borrowed_stock + $quantity,
        ])->save();

        $this->log($lockedBook, BookStockHistory::TYPE_BORROW, -$quantity, $actor, null, $reference);
    }

    private function log(Book $book, string $type, int $change, ?User $actor, ?string $notes, ?Model $reference = null): BookStockHistory
    {
        return BookStockHistory::query()->create([
            'book_id' => $book->getKey(),
            'user_id' => $actor?->getKey(),
            'type' => $type,
            'quantity_change' => $change,
            'total_after' => $book->total_stock,
            'available_after' => $book->available_stock,
            'borrowed_after' => $book->borrowed_stock,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'notes' => $notes,
        ]);
    }

    /**
     * Menambah stok tersedia dan mengurangi stok dipinjam saat buku dikembalikan.
     * $lockedBook WAJIB sudah dikunci (lockForUpdate) oleh pemanggil di dalam transaksi.
     *
     * @throws BusinessRuleException jika jumlah dipinjam lebih kecil dari yang dikembalikan
     */
    public function receive(Book $lockedBook, int $quantity, ?User $actor, Model $reference): void
    {
        if ($quantity < 1) {
            throw new BusinessRuleException('Jumlah pengembalian tidak valid.');
        }

        if ($lockedBook->borrowed_stock < $quantity) {
            throw new BusinessRuleException(
                "Data stok buku \"{$lockedBook->title}\" tidak konsisten: jumlah dipinjam lebih kecil dari jumlah yang dikembalikan."
            );
        }

        $lockedBook->forceFill([
            'available_stock' => $lockedBook->available_stock + $quantity,
            'borrowed_stock' => $lockedBook->borrowed_stock - $quantity,
        ])->save();

        $this->log($lockedBook, BookStockHistory::TYPE_RETURN, $quantity, $actor, null, $reference);
    }
}
