<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super-admin';
    case AdminMaster = 'admin-master';
    case AdminKaryawan = 'admin-karyawan';
    case Anggota = 'anggota';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AdminMaster => 'Admin Master',
            self::AdminKaryawan => 'Admin Karyawan',
            self::Anggota => 'Anggota',
        };
    }

    /** Nama route dashboard, contoh: "super-admin.dashboard". */
    public function dashboardRoute(): string
    {
        return $this->value . '.dashboard';
    }

    public function isStaff(): bool
    {
        return $this !== self::Anggota;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
