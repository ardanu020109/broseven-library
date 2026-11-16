<?php

namespace App\Services;

use App\Enums\BorrowingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\LibraryProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BorrowingService
{
    public function __construct(private readonly BookStockService $stock)
    {
    }

    /** Alasan anggota belum boleh mengajukan peminjaman, atau null jika boleh. */
    public function eligibilityProblem(User $member): ?string
    {
        if (! $member->is_active) {
            return 'Akun Anda tidak aktif.';
        }

        $profile = $member->memberProfile;

        if (! $profile) {
            return 'Anda belum memiliki data keanggotaan. Hubungi petugas perpustakaan.';
        }

        if (! $profile->is_valid) {
            return 'Keanggotaan Anda tidak berlaku (ditangguhkan atau sudah kedaluwarsa). Hubungi petugas perpustakaan.';
        }

        $overdue = Borrowing::query()
            ->forMember($member->id)
            ->whereIn('status', BorrowingStatus::stockHoldingValues())
            ->where('due_date', '<', today()->toDateString())
            ->count();

        if ($overdue > 0) {
            return "Anda memiliki {$overdue} peminjaman yang sudah lewat jatuh tempo. Kembalikan buku terlebih dahulu.";
        }

        return null;
    }

    /**
     * Anggota mengajukan peminjaman. Stok TIDAK dikurangi pada tahap ini.
     *
     * @param  array<int, int|string>  $bookIds
     *
     * @throws BusinessRuleException
     */
    public function request(User $member, array $bookIds, ?string $notes = null): Borrowing
    {
        if ($problem = $this->eligibilityProblem($member)) {
            throw new BusinessRuleException($problem);
        }

        $ids = collect($bookIds)->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values();
        $max = max(1, (int) LibraryProfile::current()->max_books_per_loan);

        if ($ids->isEmpty()) {
            throw new BusinessRuleException('Pilih minimal satu buku.');
        }

        if ($ids->count() > $max) {
            throw new BusinessRuleException("Maksimal {$max} buku dalam satu peminjaman.");
        }

        return DB::transaction(function () use ($member, $ids, $notes) {
            $books = Book::query()->whereIn('id', $ids)->get()->keyBy('id');

            $engaged = BorrowingItem::query()
                ->whereIn('book_id', $ids)
                ->whereHas('borrowing', fn ($b) => $b
                    ->where('member_id', $member->id)
                    ->whereIn('status', BorrowingStatus::openValues()))
                ->pluck('book_id');

            foreach ($ids as $id) {
                $book = $books->get($id);

                if (! $book || $book->status !== Book::STATUS_ACTIVE) {
                    throw new BusinessRuleException('Salah satu buku tidak ditemukan atau tidak tersedia untuk dipinjam.');
                }

                if ($book->available_stock < 1) {
                    throw new BusinessRuleException("Buku \"{$book->title}\" sedang tidak tersedia.");
                }

                if ($engaged->contains($id)) {
                    throw new BusinessRuleException("Anda sudah meminjam atau mengajukan buku \"{$book->title}\".");
                }
            }

            $borrowing = Borrowing::create([
                'code' => Borrowing::generateCode(),
                'member_id' => $member->id,
                'requested_at' => now(),
                'status' => BorrowingStatus::Pending,
                'notes' => $notes,
            ]);

            foreach ($ids as $id) {
                $borrowing->items()->create(['book_id' => $id, 'quantity' => 1]);
            }

            ActivityLog::record(
                'borrowing.requested',
                "Mengajukan peminjaman {$borrowing->code} ({$ids->count()} buku)",
                $borrowing,
                ['books' => $books->pluck('title')->values()->all()],
                $member->id,
            );

            return $borrowing;
        });
    }

    /**
     * Anggota membatalkan pengajuannya yang masih menunggu persetujuan.
     *
     * @throws BusinessRuleException
     */
    public function cancel(Borrowing $borrowing, User $actor): void
    {
        DB::transaction(function () use ($borrowing, $actor) {
            // Kunci baris agar tidak bentrok dengan persetujuan yang sedang berjalan.
            $locked = Borrowing::query()->lockForUpdate()->findOrFail($borrowing->id);

            if ($locked->status !== BorrowingStatus::Pending) {
                throw new BusinessRuleException('Hanya pengajuan yang masih menunggu persetujuan yang dapat dibatalkan.');
            }

            $locked->update(['status' => BorrowingStatus::Cancelled]);

            ActivityLog::record('borrowing.cancelled', "Membatalkan pengajuan {$locked->code}", $locked, [], $actor->id);
        });

        $borrowing->refresh();
    }

    /**
     * Petugas menyetujui pengajuan: stok semua buku dikurangi atau tidak sama sekali.
     *
     * @throws BusinessRuleException
     */
    public function approve(Borrowing $borrowing, User $staff): Borrowing
    {
        return DB::transaction(function () use ($borrowing, $staff) {
            $locked = Borrowing::query()->lockForUpdate()->with('items')->findOrFail($borrowing->id);

            // Diperiksa ulang SETELAH dikunci: mencegah persetujuan ganda.
            if ($locked->status !== BorrowingStatus::Pending) {
                throw new BusinessRuleException('Pengajuan ini sudah diproses sebelumnya.');
            }

            $member = User::query()->with('memberProfile')->find($locked->member_id);

            if (! $member || ! $member->is_active) {
                throw new BusinessRuleException('Akun anggota tidak aktif.');
            }

            if (! $member->memberProfile?->is_valid) {
                throw new BusinessRuleException('Keanggotaan anggota tidak berlaku.');
            }

            // Kunci semua buku dengan urutan ID yang sama agar dua persetujuan bersamaan tidak saling menunggu (deadlock).
            $books = Book::query()
                ->whereIn('id', $locked->items->pluck('book_id')->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($locked->items as $item) {
                $book = $books->get($item->book_id);

                if (! $book) {
                    throw new BusinessRuleException('Salah satu buku sudah dihapus dari koleksi.');
                }

                $this->stock->lend($book, $item->quantity, $staff, $locked);
            }

            $locked->update([
                'status' => BorrowingStatus::Borrowed,
                'approved_at' => now(),
                'borrow_date' => today()->toDateString(),
                'due_date' => today()->addDays(max(1, (int) LibraryProfile::current()->loan_duration_days))->toDateString(),
                'processed_by' => $staff->id,
            ]);

            ActivityLog::record(
                'borrowing.approved',
                "Menyetujui peminjaman {$locked->code} untuk {$member->name}",
                $locked,
                ['due_date' => $locked->due_date->toDateString()],
                $staff->id,
            );

            return $locked;
        });
    }

    /**
     * @throws BusinessRuleException
     */
    public function reject(Borrowing $borrowing, User $staff, string $reason): void
    {
        DB::transaction(function () use ($borrowing, $staff, $reason) {
            $locked = Borrowing::query()->lockForUpdate()->findOrFail($borrowing->id);

            if ($locked->status !== BorrowingStatus::Pending) {
                throw new BusinessRuleException('Pengajuan ini sudah diproses sebelumnya.');
            }

            $locked->update([
                'status' => BorrowingStatus::Rejected,
                'rejection_reason' => $reason,
                'processed_by' => $staff->id,
            ]);

            ActivityLog::record('borrowing.rejected', "Menolak peminjaman {$locked->code}", $locked, ['reason' => $reason], $staff->id);
        });

        $borrowing->refresh();
    }

    /**
     * Pratinjau masalah yang akan menggagalkan persetujuan (tanpa mengunci atau mengubah data).
     * Persetujuan sebenarnya tetap divalidasi ulang di dalam approve().
     *
     * @return array<int, string>
     */
    public function approvalProblems(Borrowing $borrowing): array
    {
        $problems = [];
        $member = $borrowing->member;

        if (! $member->is_active) {
            $problems[] = 'Akun anggota tidak aktif.';
        } elseif (! $member->memberProfile?->is_valid) {
            $problems[] = 'Keanggotaan anggota tidak berlaku.';
        }

        foreach ($borrowing->items as $item) {
            $book = $item->book;

            if (! $book || $book->trashed()) {
                $problems[] = 'Salah satu buku sudah dihapus dari koleksi.';
            } elseif ($book->status !== Book::STATUS_ACTIVE) {
                $problems[] = "Buku \"{$book->title}\" sedang dinonaktifkan.";
            } elseif ($book->available_stock < $item->quantity) {
                $problems[] = "Stok buku \"{$book->title}\" tidak mencukupi (tersedia {$book->available_stock}).";
            }
        }

        return $problems;
    }

    /** Menandai peminjaman yang lewat jatuh tempo sebagai "overdue". Mengembalikan jumlah yang diubah. */
    public function markOverdue(): int
    {
        return Borrowing::query()
            ->whereIn('status', [
                BorrowingStatus::Approved->value,
                BorrowingStatus::Borrowed->value,
                BorrowingStatus::PartiallyReturned->value,
            ])
            ->where('due_date', '<', today()->toDateString())
            ->update(['status' => BorrowingStatus::Overdue->value]);
    }
}
