<?php

namespace App\Services;

use App\Enums\BorrowingStatus;
use App\Enums\FineStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Fine;
use App\Models\LibraryProfile;
use App\Models\ReturnTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function __construct(
        private readonly BookStockService $stock,
        private readonly FineCalculator $fines,
    ) {
    }

    /**
     * Memproses pengembalian (penuh atau sebagian) dalam satu transaksi database.
     *
     * @param  array<int|string, int|string|null>  $quantities  [borrowing_item_id => jumlah dikembalikan]
     *
     * @throws BusinessRuleException
     */
    public function process(Borrowing $borrowing, array $quantities, User $staff, ?string $notes = null): ReturnTransaction
    {
        $quantities = collect($quantities)
            ->mapWithKeys(fn ($qty, $itemId) => [(int) $itemId => (int) $qty])
            ->filter(fn (int $qty) => $qty > 0);

        if ($quantities->isEmpty()) {
            throw new BusinessRuleException('Pilih minimal satu buku yang dikembalikan.');
        }

        return DB::transaction(function () use ($borrowing, $quantities, $staff, $notes) {
            // Kunci peminjaman dan semua itemnya; status diperiksa ulang SETELAH dikunci.
            $locked = Borrowing::query()->lockForUpdate()->findOrFail($borrowing->id);

            if (! $locked->status->holdsStock()) {
                throw new BusinessRuleException('Peminjaman ini tidak sedang berjalan sehingga tidak bisa diproses pengembaliannya.');
            }

            $items = BorrowingItem::query()
                ->where('borrowing_id', $locked->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($quantities as $itemId => $qty) {
                $item = $items->get($itemId);

                if (! $item) {
                    throw new BusinessRuleException('Buku yang dipilih bukan bagian dari peminjaman ini.');
                }

                // Mencegah pengembalian ganda.
                if ($qty > $item->outstanding_quantity) {
                    throw new BusinessRuleException(
                        "Jumlah yang dikembalikan ({$qty}) melebihi sisa yang belum dikembalikan ({$item->outstanding_quantity})."
                    );
                }
            }

            // Kunci semua buku dengan urutan ID yang konsisten agar tidak deadlock.
            $books = Book::withTrashed()
                ->whereIn('id', $quantities->keys()->map(fn (int $id) => $items->get($id)->book_id)->unique()->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $returnDate = today();
            $dueDate = $locked->due_date ?? $returnDate;
            $perDay = (float) LibraryProfile::current()->fine_per_day;

            $return = ReturnTransaction::create([
                'code' => ReturnTransaction::generateCode(),
                'borrowing_id' => $locked->id,
                'return_date' => $returnDate->toDateString(),
                'processed_by' => $staff->id,
                'notes' => $notes,
            ]);

            $totalFine = 0.0;

            foreach ($quantities as $itemId => $qty) {
                $item = $items->get($itemId);
                $calc = $this->fines->calculate($dueDate, $returnDate, $perDay, $qty);

                $returnItem = $return->items()->create([
                    'borrowing_item_id' => $item->id,
                    'quantity' => $qty,
                    // Snapshot: perubahan tarif di masa depan tidak mengubah riwayat ini.
                    'due_date' => $dueDate->toDateString(),
                    'days_late' => $calc['days_late'],
                    'fine_per_day' => $calc['fine_per_day'],
                    'fine_amount' => $calc['fine_amount'],
                ]);

                if ($calc['fine_amount'] > 0) {
                    Fine::create([
                        'return_item_id' => $returnItem->id,
                        'member_id' => $locked->member_id,
                        'amount' => $calc['fine_amount'],
                        'paid_amount' => 0,
                        'status' => FineStatus::Unpaid,
                    ]);

                    $totalFine += $calc['fine_amount'];
                }

                $returned = $item->returned_quantity + $qty;
                $item->update([
                    'returned_quantity' => $returned,
                    'return_status' => $returned >= $item->quantity
                        ? BorrowingItem::RETURN_RETURNED
                        : BorrowingItem::RETURN_PARTIAL,
                ]);

                $this->stock->receive($books->get($item->book_id), $qty, $staff, $return);
            }

            $stillOutstanding = BorrowingItem::query()
                ->where('borrowing_id', $locked->id)
                ->whereColumn('returned_quantity', '<', 'quantity')
                ->exists();

            $locked->update([
                'status' => $stillOutstanding ? BorrowingStatus::PartiallyReturned : BorrowingStatus::Returned,
            ]);

            ActivityLog::record(
                'return.processed',
                "Memproses pengembalian {$return->code} untuk peminjaman {$locked->code}",
                $return,
                ['total_fine' => $totalFine, 'complete' => ! $stillOutstanding],
                $staff->id,
            );

            return $return;
        });
    }
}
