<?php

namespace App\Enums;

enum FineStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Dibayar',
            self::Paid => 'Lunas',
            self::Waived => 'Dibebaskan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unpaid => 'badge-overdue',
            self::Paid => 'badge-available',
            self::Waived => 'badge-returned',
        };
    }
}
