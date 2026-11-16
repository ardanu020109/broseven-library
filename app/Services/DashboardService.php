<?php

namespace App\Services;

use App\Enums\BorrowingStatus;
use App\Enums\FineStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Author;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Category;
use App\Models\Fine;
use App\Models\MemberProfile;
use App\Models\Publisher;
use App\Models\ReturnTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** Anggota diingatkan untuk buku yang jatuh tempo dalam sekian hari. */
    public const DUE_SOON_DAYS = 3;

    // =====================================================================
    // Super Admin
    // =====================================================================

    /** @return array<string, mixed> */
    public function superAdmin(): array
    {
        return [
            'stats' => [
                'books' => Book::query()->count(),
                'copies' => (int) Book::query()->sum('total_stock'),
                'available' => (int) Book::query()->sum('available_stock'),
                'members' => User::query()->role(Role::Anggota)->count(),
                'admins' => User::query()->whereIn('role', [
                    Role::SuperAdmin->value,
                    Role::AdminMaster->value,
                    Role::AdminKaryawan->value,
                ])->count(),
                'active_loans' => $this->holdingBorrowings()->count(),
                'returns' => ReturnTransaction::query()->count(),
                'overdue_books' => $this->overdueBooks(),
                'fines_total' => (float) Fine::query()->sum('amount'),
                'fines_unpaid' => $this->unpaidFines(),
            ],
            'charts' => [
                'borrowings' => $this->monthlySeries(
                    Borrowing::query()
                        ->whereNotNull('borrow_date')
                        ->whereNotIn('status', $this->nonLoanStatuses()),
                    'borrow_date',
                    'Peminjaman',
                ),
                'returns' => $this->monthlySeries(ReturnTransaction::query(), 'return_date', 'Pengembalian'),
            ],
            'popularBooks' => $this->popularBooks(),
            'activities' => ActivityLog::query()
                ->with('user:id,name,role')
                ->latest()->latest('id')
                ->take(10)->get(),
        ];
    }

    // =====================================================================
    // Admin Master
    // =====================================================================

    /** @return array<string, mixed> */
    public function adminMaster(): array
    {
        $threshold = (int) Setting::get('low_stock_threshold', 2);

        return [
            'threshold' => $threshold,
            'stats' => [
                'books' => Book::query()->count(),
                'categories' => Category::query()->count(),
                'authors' => Author::query()->count(),
                'publishers' => Publisher::query()->count(),
                'copies' => (int) Book::query()->sum('total_stock'),
                'available' => (int) Book::query()->sum('available_stock'),
                'low_stock' => Book::query()->active()->lowStock($threshold)->count(),
            ],
            'lowStockBooks' => Book::query()->active()
                ->with('author:id,name')
                ->lowStock($threshold)
                ->orderBy('available_stock')->orderBy('title')
                ->take(8)->get(),
            'latestBooks' => Book::query()
                ->with(['author:id,name', 'category:id,name'])
                ->latest()->latest('id')
                ->take(8)->get(),
        ];
    }

    // =====================================================================
    // Admin Karyawan
    // =====================================================================

    /** @return array<string, mixed> */
    public function adminKaryawan(): array
    {
        $today = today()->toDateString();

        return [
            'stats' => [
                'loans_today' => Borrowing::query()
                    ->where('borrow_date', $today)
                    ->whereNotIn('status', $this->nonLoanStatuses())
                    ->count(),
                'returns_today' => ReturnTransaction::query()->where('return_date', $today)->count(),
                'pending' => Borrowing::query()->where('status', BorrowingStatus::Pending->value)->count(),
                'borrowed_books' => $this->outstandingBooks(),
                'overdue_books' => $this->overdueBooks(),
                'fines_unpaid' => $this->unpaidFines(),
            ],
            'pendingRequests' => Borrowing::query()
                ->with('member:id,name')
                ->withSum('items as total_items', 'quantity')
                ->where('status', BorrowingStatus::Pending->value)
                ->oldest('requested_at')
                ->take(6)->get(),
            'recentBorrowings' => Borrowing::query()
                ->with('member:id,name')
                ->withSum('items as total_items', 'quantity')
                ->latest('requested_at')->latest('id')
                ->take(8)->get(),
        ];
    }

    // =====================================================================
    // Anggota (hanya data milik anggota yang login)
    // =====================================================================

    /** @return array<string, mixed> */
    public function anggota(User $user): array
    {
        $today = today();

        $holdingMine = fn (): Builder => Borrowing::query()
            ->forMember($user->id)
            ->whereIn('status', BorrowingStatus::stockHoldingValues());

        $dueSoon = $holdingMine()
            ->whereBetween('due_date', [
                $today->toDateString(),
                $today->copy()->addDays(self::DUE_SOON_DAYS)->toDateString(),
            ])
            ->with('items.book:id,title')
            ->orderBy('due_date')
            ->get();

        return [
            'stats' => [
                'active_books' => (int) BorrowingItem::query()
                    ->whereHas('borrowing', fn (Builder $b) => $b
                        ->where('member_id', $user->id)
                        ->whereIn('status', BorrowingStatus::stockHoldingValues()))
                    ->sum(DB::raw('quantity - returned_quantity')),
                'history' => Borrowing::query()->forMember($user->id)->count(),
                'due_soon' => $dueSoon->count(),
                'overdue' => $holdingMine()->where('due_date', '<', $today->toDateString())->count(),
                'fines_unpaid' => $this->unpaidFines($user->id),
            ],
            'dueSoonDays' => self::DUE_SOON_DAYS,
            'dueSoon' => $dueSoon,
            'recentBorrowings' => Borrowing::query()
                ->forMember($user->id)
                ->with('items.book:id,title')
                ->latest('requested_at')->latest('id')
                ->take(5)->get(),
            'membership' => $this->membership($user),
        ];
    }

    // =====================================================================
    // Helper
    // =====================================================================

    /** @return array<int, string> */
    private function nonLoanStatuses(): array
    {
        return [
            BorrowingStatus::Pending->value,
            BorrowingStatus::Rejected->value,
            BorrowingStatus::Cancelled->value,
        ];
    }

    private function holdingBorrowings(): Builder
    {
        return Borrowing::query()->whereIn('status', BorrowingStatus::stockHoldingValues());
    }

    /** Jumlah eksemplar yang sedang berada di tangan anggota. */
    private function outstandingBooks(?callable $constraint = null): int
    {
        return (int) BorrowingItem::query()
            ->whereHas('borrowing', function (Builder $borrowing) use ($constraint) {
                $borrowing->whereIn('status', BorrowingStatus::stockHoldingValues());

                if ($constraint) {
                    $constraint($borrowing);
                }
            })
            ->sum(DB::raw('quantity - returned_quantity'));
    }

    /** Eksemplar yang belum kembali padahal sudah lewat jatuh tempo. */
    private function overdueBooks(): int
    {
        return $this->outstandingBooks(
            fn (Builder $borrowing) => $borrowing->where('due_date', '<', today()->toDateString())
        );
    }

    /** Sisa denda yang belum dibayar (jumlah denda dikurangi yang sudah dibayar). */
    private function unpaidFines(?int $memberId = null): float
    {
        return (float) Fine::query()
            ->where('status', FineStatus::Unpaid->value)
            ->when($memberId, fn (Builder $q) => $q->where('member_id', $memberId))
            ->sum(DB::raw('amount - paid_amount'));
    }

    private function popularBooks(): Collection
    {
        return Book::query()->active()
            ->with('author:id,name')
            ->popular()->orderBy('title')
            ->take(5)->get()
            ->filter(fn (Book $book) => (int) $book->borrowed_total > 0)
            ->values();
    }

    /**
     * Jumlah baris per bulan untuk 12 bulan terakhir (bulan tanpa data bernilai 0).
     *
     * @return array{labels: array<int, string>, values: array<int, int>, label: string}
     */
    private function monthlySeries(Builder $query, string $column, string $label, int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $expression = $this->monthExpression($column);

        $totals = $query
            ->where($column, '>=', $start->toDateString())
            ->selectRaw("{$expression} as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $labels = [];
        $values = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $labels[] = $month->translatedFormat('M Y');
            $values[] = (int) ($totals[$month->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values, 'label' => $label];
    }

    /** Ekspresi "YYYY-MM" sesuai driver. $column selalu string tetap dari kode ini, bukan input pengguna. */
    private function monthExpression(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /** @return array{label: string, valid: bool, number: ?string, joined_at: mixed, expired_at: mixed} */
    private function membership(User $user): array
    {
        $profile = $user->memberProfile;

        if (! $profile) {
            return ['label' => 'Belum terdaftar', 'valid' => false, 'number' => null, 'joined_at' => null, 'expired_at' => null];
        }

        $label = match (true) {
            $profile->status === MemberProfile::STATUS_SUSPENDED => 'Ditangguhkan',
            $profile->status === MemberProfile::STATUS_EXPIRED,
            $profile->expired_at !== null && $profile->expired_at->lt(today()) => 'Kedaluwarsa',
            default => 'Aktif',
        };

        return [
            'label' => $label,
            'valid' => $profile->is_valid,
            'number' => $profile->member_number,
            'joined_at' => $profile->joined_at,
            'expired_at' => $profile->expired_at,
        ];
    }
}
