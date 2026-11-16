<?php

namespace App\Services;

use App\Enums\BorrowingStatus;
use App\Enums\FineStatus;
use App\Models\BorrowingItem;
use App\Models\ReturnItem;
use App\Support\ReportFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    // ======================================================================
    // Laporan peminjaman (satu baris per judul buku)
    // ======================================================================

    /** Join dan filter tanpa kolom terpilih, dipakai untuk daftar, hitungan, dan ringkasan. */
    public function borrowingBase(ReportFilters $filters): Builder
    {
        return BorrowingItem::query()
            ->join('borrowings', 'borrowings.id', '=', 'borrowing_items.borrowing_id')
            ->join('users', 'users.id', '=', 'borrowings.member_id')
            ->join('books', 'books.id', '=', 'borrowing_items.book_id')
            // Tanggal peminjaman; pengajuan yang belum disetujui memakai tanggal pengajuan.
            ->when($filters->from, fn ($q) => $q->whereRaw(
                'COALESCE(borrowings.borrow_date, DATE(borrowings.requested_at)) >= ?',
                [$filters->from->toDateString()],
            ))
            ->when($filters->to, fn ($q) => $q->whereRaw(
                'COALESCE(borrowings.borrow_date, DATE(borrowings.requested_at)) <= ?',
                [$filters->to->toDateString()],
            ))
            ->when($filters->member !== '', fn ($q) => $q->where(function ($w) use ($filters) {
                $w->where('users.name', 'like', "%{$filters->member}%")
                    ->orWhere('users.username', 'like', "%{$filters->member}%");
            }))
            ->when($filters->book !== '', fn ($q) => $q->where('books.title', 'like', "%{$filters->book}%"))
            // "Terlambat" dihitung dari tanggal jatuh tempo, bukan hanya dari status tersimpan.
            ->when($filters->status === 'overdue', fn ($q) => $q
                ->whereIn('borrowings.status', BorrowingStatus::stockHoldingValues())
                ->where('borrowings.due_date', '<', today()->toDateString())
                ->whereColumn('borrowing_items.returned_quantity', '<', 'borrowing_items.quantity'))
            ->when($filters->status !== '' && $filters->status !== 'overdue', fn ($q) => $q
                ->where('borrowings.status', $filters->status));
    }

    public function borrowingQuery(ReportFilters $filters): Builder
    {
        return $this->borrowingBase($filters)
            ->select([
                'borrowing_items.id',
                'borrowing_items.quantity',
                'borrowing_items.returned_quantity',
                'borrowings.code as borrowing_code',
                'borrowings.status as borrowing_status',
                'borrowings.borrow_date',
                'borrowings.due_date',
                'users.name as member_name',
                'books.title as book_title',
            ])
            ->selectSub(
                ReturnItem::query()
                    ->join('returns', 'returns.id', '=', 'return_items.return_id')
                    ->whereColumn('return_items.borrowing_item_id', 'borrowing_items.id')
                    ->selectRaw('MAX(returns.return_date)'),
                'returned_on',
            )
            ->orderByRaw('COALESCE(borrowings.borrow_date, DATE(borrowings.requested_at)) DESC')
            ->orderByDesc('borrowing_items.id');
    }

    /** @return array{transactions: int, items: int, books: int} */
    public function borrowingSummary(ReportFilters $filters): array
    {
        $row = $this->borrowingBase($filters)->toBase()
            ->selectRaw('COUNT(DISTINCT borrowings.id) AS transactions, COUNT(*) AS items, COALESCE(SUM(borrowing_items.quantity), 0) AS books')
            ->first();

        return [
            'transactions' => (int) $row->transactions,
            'items' => (int) $row->items,
            'books' => (int) $row->books,
        ];
    }

    /**
     * @return array{no: int, code: string, member: string, book: string, quantity: int, borrow_date: string, due_date: string, returned_on: string, status: string}
     */
    public function formatBorrowingRow(BorrowingItem $row, int $no): array
    {
        $status = BorrowingStatus::from($row->borrowing_status);
        $outstanding = $row->returned_quantity < $row->quantity;
        $late = $outstanding
            && $status->holdsStock()
            && $row->due_date
            && Carbon::parse($row->due_date)->lt(today());

        $label = match (true) {
            ! $outstanding => 'Dikembalikan',
            $late => 'Terlambat',
            default => $status->label(),
        };

        return [
            'no' => $no,
            'code' => $row->borrowing_code,
            'member' => $row->member_name,
            'book' => $row->book_title,
            'quantity' => (int) $row->quantity,
            'borrow_date' => $this->date($row->borrow_date),
            'due_date' => $this->date($row->due_date),
            'returned_on' => $this->date($row->returned_on),
            'status' => $label,
        ];
    }

    // ======================================================================
    // Laporan pengembalian (satu baris per judul yang dikembalikan)
    // ======================================================================

    public function returnBase(ReportFilters $filters): Builder
    {
        return ReturnItem::query()
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->join('borrowing_items', 'borrowing_items.id', '=', 'return_items.borrowing_item_id')
            ->join('borrowings', 'borrowings.id', '=', 'returns.borrowing_id')
            ->join('users as members', 'members.id', '=', 'borrowings.member_id')
            ->join('books', 'books.id', '=', 'borrowing_items.book_id')
            ->leftJoin('users as staff', 'staff.id', '=', 'returns.processed_by')
            ->leftJoin('fines', 'fines.return_item_id', '=', 'return_items.id')
            ->when($filters->from, fn ($q) => $q->where('returns.return_date', '>=', $filters->from->toDateString()))
            ->when($filters->to, fn ($q) => $q->where('returns.return_date', '<=', $filters->to->toDateString()))
            ->when($filters->member !== '', fn ($q) => $q->where(function ($w) use ($filters) {
                $w->where('members.name', 'like', "%{$filters->member}%")
                    ->orWhere('members.username', 'like', "%{$filters->member}%");
            }))
            ->when($filters->book !== '', fn ($q) => $q->where('books.title', 'like', "%{$filters->book}%"))
            ->when($filters->returnStatus === 'on_time', fn ($q) => $q->where('return_items.days_late', 0))
            ->when($filters->returnStatus === 'late', fn ($q) => $q->where('return_items.days_late', '>', 0))
            ->when($filters->fineStatus === 'none', fn ($q) => $q->whereNull('fines.id'))
            ->when(in_array($filters->fineStatus, ['unpaid', 'paid', 'waived'], true), fn ($q) => $q
                ->where('fines.status', $filters->fineStatus));
    }

    public function returnQuery(ReportFilters $filters): Builder
    {
        return $this->returnBase($filters)
            ->select([
                'return_items.id',
                'return_items.quantity',
                'return_items.due_date',
                'return_items.days_late',
                'return_items.fine_amount',
                'returns.code as return_code',
                'returns.return_date',
                'borrowings.code as borrowing_code',
                'borrowings.borrow_date',
                'members.name as member_name',
                'books.title as book_title',
                'staff.name as staff_name',
                'fines.status as fine_status',
            ])
            ->orderByDesc('returns.return_date')
            ->orderByDesc('return_items.id');
    }

    /** @return array{returns_count: int, items: int, books: int, fine_total: float, late_items: int} */
    public function returnSummary(ReportFilters $filters): array
    {
        $row = $this->returnBase($filters)->toBase()
            ->selectRaw('COUNT(DISTINCT returns.id) AS returns_count, COUNT(*) AS items, COALESCE(SUM(return_items.quantity), 0) AS books, COALESCE(SUM(return_items.fine_amount), 0) AS fine_total, COALESCE(SUM(CASE WHEN return_items.days_late > 0 THEN 1 ELSE 0 END), 0) AS late_items')
            ->first();

        return [
            'returns_count' => (int) $row->returns_count,
            'items' => (int) $row->items,
            'books' => (int) $row->books,
            'fine_total' => (float) $row->fine_total,
            'late_items' => (int) $row->late_items,
        ];
    }

    /**
     * @return array{no: int, return_code: string, borrowing_code: string, member: string, book: string, borrow_date: string, due_date: string, return_date: string, days_late: int, fine: float, fine_status: string, staff: string}
     */
    public function formatReturnRow(ReturnItem $row, int $no): array
    {
        return [
            'no' => $no,
            'return_code' => $row->return_code,
            'borrowing_code' => $row->borrowing_code,
            'member' => $row->member_name,
            'book' => $row->book_title,
            'borrow_date' => $this->date($row->borrow_date),
            'due_date' => $this->date($row->due_date),
            'return_date' => $this->date($row->return_date),
            'days_late' => (int) $row->days_late,
            'fine' => (float) $row->fine_amount,
            'fine_status' => $row->fine_status ? FineStatus::from($row->fine_status)->label() : 'Tanpa denda',
            'staff' => $row->staff_name ?? '-',
        ];
    }

    private function date(mixed $value): string
    {
        return $value ? Carbon::parse($value)->translatedFormat('d M Y') : '-';
    }
}
