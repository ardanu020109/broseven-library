<?php

namespace App\Support;

use App\Enums\Role;
use Illuminate\Support\Facades\Route;

final class Menu
{
    /**
     * @return array<int, array{title: ?string, items: array<int, array{label: string, icon: string, route: string, url: ?string, enabled: bool, active: bool}>}>
     */
    public static function for(Role $role): array
    {
        $groups = [];

        foreach (self::definition($role) as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                $route = ($item['absolute'] ?? false) ? $item['route'] : $role->value . '.' . $item['route'];
                $enabled = Route::has($route);

                $items[] = [
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'route' => $route,
                    'url' => $enabled ? route($route) : null,
                    'enabled' => $enabled,
                    'active' => $enabled && request()->routeIs(...self::patterns($route)),
                ];
            }

            $groups[] = ['title' => $group['title'], 'items' => $items];
        }

        return $groups;
    }

    /** @return array<int, string> */
    private static function patterns(string $route): array
    {
        if (str_ends_with($route, '.index')) {
            $base = substr($route, 0, -strlen('.index'));

            return [$route, $base . '.create', $base . '.show', $base . '.edit'];
        }

        return [$route];
    }

    private static function item(string $label, string $icon, string $route, bool $absolute = false): array
    {
        return ['label' => $label, 'icon' => $icon, 'route' => $route, 'absolute' => $absolute];
    }

    /** @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}> */
    private static function definition(Role $role): array
    {
        return match ($role) {
            Role::SuperAdmin => [
                ['title' => null, 'items' => [
                    self::item('Dashboard', 'bi-speedometer2', 'dashboard'),
                ]],
                ['title' => 'Master Data', 'items' => [
                    self::item('Buku', 'bi-journal-bookmark', 'books.index'),
                    self::item('Kategori', 'bi-tags', 'categories.index'),
                    self::item('Penulis', 'bi-person-lines-fill', 'authors.index'),
                    self::item('Penerbit', 'bi-building', 'publishers.index'),
                    self::item('Rak Buku', 'bi-bookshelf', 'shelves.index'),
                ]],
                ['title' => 'Pengguna', 'items' => [
                    self::item('Pengguna', 'bi-people', 'users.index'),
                ]],
                ['title' => 'Transaksi', 'items' => [
                    self::item('Peminjaman', 'bi-journal-arrow-up', 'borrowings.index'),
                    self::item('Pengembalian', 'bi-journal-arrow-down', 'returns.index'),
                    self::item('Denda', 'bi-cash-coin', 'fines.index'),
                ]],
                ['title' => 'Laporan', 'items' => [
                    self::item('Laporan Peminjaman', 'bi-file-earmark-bar-graph', 'reports.borrowings'),
                    self::item('Laporan Pengembalian', 'bi-file-earmark-text', 'reports.returns'),
                ]],
                ['title' => 'Konten & Sistem', 'items' => [
                    self::item('Profil Perpustakaan', 'bi-bank', 'library-profile.edit'),
                    self::item('Blog', 'bi-newspaper', 'blogs.index'),
                    self::item('Log Aktivitas', 'bi-activity', 'activity-logs.index'),
                ]],
            ],

            Role::AdminMaster => [
                ['title' => null, 'items' => [
                    self::item('Dashboard', 'bi-speedometer2', 'dashboard'),
                ]],
                ['title' => 'Master Data', 'items' => [
                    self::item('Buku', 'bi-journal-bookmark', 'books.index'),
                    self::item('Kategori', 'bi-tags', 'categories.index'),
                    self::item('Penulis', 'bi-person-lines-fill', 'authors.index'),
                    self::item('Penerbit', 'bi-building', 'publishers.index'),
                    self::item('Rak Buku', 'bi-bookshelf', 'shelves.index'),
                ]],
            ],

            Role::AdminKaryawan => [
                ['title' => null, 'items' => [
                    self::item('Dashboard', 'bi-speedometer2', 'dashboard'),
                ]],
                ['title' => 'Layanan', 'items' => [
                    self::item('Peminjaman', 'bi-journal-arrow-up', 'borrowings.index'),
                    self::item('Buku Dipinjam', 'bi-book-half', 'borrowings.active'),
                    self::item('Pengembalian', 'bi-journal-arrow-down', 'returns.index'),
                    self::item('Denda', 'bi-cash-coin', 'fines.index'),
                ]],
                ['title' => 'Anggota', 'items' => [
                    self::item('Data Anggota', 'bi-people', 'members.index'),
                ]],
            ],

            Role::Anggota => [
                ['title' => null, 'items' => [
                    self::item('Dashboard', 'bi-speedometer2', 'dashboard'),
                ]],
                ['title' => 'Perpustakaan', 'items' => [
                    self::item('Katalog Buku', 'bi-search', 'catalog.index'),
                ]],
                ['title' => 'Peminjaman', 'items' => [
                    self::item('Peminjaman Saya', 'bi-journal-arrow-up', 'borrowings.index'),
                    self::item('Riwayat', 'bi-clock-history', 'borrowings.history'),
                    self::item('Denda Saya', 'bi-cash-coin', 'fines.index'),
                ]],
                ['title' => 'Akun', 'items' => [
                    self::item('Kartu Anggota', 'bi-person-vcard', 'membership-card.show'),
                    self::item('Profil Saya', 'bi-person-circle', 'profile.edit', true),
                ]],
                ['title' => 'Informasi', 'items' => [
                    self::item('Tentang Perpustakaan', 'bi-info-circle', 'about', true),
                    self::item('Blog Perpustakaan', 'bi-newspaper', 'blog.index', true),
                ]],
            ],
        };
    }
}
