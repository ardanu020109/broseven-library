<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ActivityLabel
{
    /**
     * @return array{0: string, 1: string, 2: string} [label, ikon Bootstrap Icons, kelas warna]
     */
    public static function describe(string $action): array
    {
        return match (true) {
            $action === 'auth.login' => ['Login', 'bi-box-arrow-in-right', 'text-success'],
            $action === 'auth.logout' => ['Logout', 'bi-box-arrow-left', 'text-secondary'],
            $action === 'auth.login_failed' => ['Login gagal', 'bi-shield-exclamation', 'text-danger'],
            $action === 'auth.login_blocked' => ['Login diblokir', 'bi-slash-circle', 'text-danger'],
            $action === 'auth.register' => ['Registrasi anggota', 'bi-person-plus', 'text-primary'],
            $action === 'auth.password_reset' => ['Reset password', 'bi-key', 'text-warning'],
            $action === 'auth.forbidden' => ['Akses ditolak', 'bi-shield-x', 'text-danger'],
            str_starts_with($action, 'borrowing.') => ['Peminjaman', 'bi-journal-arrow-up', 'text-primary'],
            str_starts_with($action, 'return.') => ['Pengembalian', 'bi-journal-arrow-down', 'text-success'],
            str_starts_with($action, 'fine.') => ['Denda', 'bi-cash-coin', 'text-warning'],
            str_starts_with($action, 'book.') => ['Buku', 'bi-book', 'text-primary'],
            str_starts_with($action, 'user.') => ['Pengguna', 'bi-people', 'text-primary'],
            default => [Str::headline($action), 'bi-activity', 'text-muted'],
            str_starts_with($action, 'master.') => ['Data master', 'bi-database', 'text-primary'],
            str_starts_with($action, 'blog.') => ['Blog', 'bi-newspaper', 'text-primary'],
            str_starts_with($action, 'library.') => ['Profil perpustakaan', 'bi-bank', 'text-primary'],
            str_starts_with($action, 'report.') => ['Laporan', 'bi-file-earmark-bar-graph', 'text-primary'],
        };
    }
}
