<?php

namespace App\Enums;

enum BorrowingStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Borrowed = 'borrowed';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Borrowed => 'Dipinjam',
            self::PartiallyReturned => 'Sebagian Dikembalikan',
            self::Returned => 'Dikembalikan',
            self::Overdue => 'Terlambat',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Kelas badge (didefinisikan di public/assets/css/app.css). */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge-pending',
            self::Approved => 'badge-returned',
            self::Rejected => 'badge-overdue',
            self::Borrowed, self::PartiallyReturned => 'badge-borrowed',
            self::Returned => 'badge-available',
            self::Overdue => 'badge-overdue',
            self::Cancelled => 'bg-secondary-subtle text-secondary-emphasis',
        };
    }

    /** Status ketika buku berada di tangan anggota (stok sudah dikurangi). */
    public function holdsStock(): bool
    {
        return in_array($this, self::stockHolding(), true);
    }

    /** @return array<int, self> */
    public static function stockHolding(): array
    {
        return [self::Approved, self::Borrowed, self::PartiallyReturned, self::Overdue];
    }

    /** @return array<int, string> */
    public static function stockHoldingValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::stockHolding());
    }

    /** Transaksi yang belum selesai: menunggu persetujuan atau buku masih dipinjam. */
    /** @return array<int, string> */
    public static function openValues(): array
    {
        return [self::Pending->value, ...self::stockHoldingValues()];
    }
}
