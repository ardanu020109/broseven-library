<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->where('username', 'superadmin')->firstOrFail();

        $categories = [];
        foreach ([['Berita Perpustakaan', 'berita-perpustakaan'], ['Tips Membaca', 'tips-membaca'], ['Resensi Buku', 'resensi-buku']] as [$name, $slug]) {
            $categories[$slug] = BlogCategory::query()->updateOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        $posts = [
            [
                'slug' => 'selamat-datang-di-broseven-library',
                'category' => 'berita-perpustakaan',
                'title' => 'Selamat Datang di BroSeven Library',
                'excerpt' => 'Perkenalan singkat layanan dan fasilitas BroSeven Library.',
                'content' => "BroSeven Library hadir sebagai perpustakaan modern yang mudah diakses oleh siapa saja.\n\n"
                    . "Anggota dapat mencari koleksi lewat katalog online, mengajukan peminjaman, dan memantau status pinjaman langsung dari akun masing-masing.\n\n"
                    . "Daftarkan diri Anda sebagai anggota dan mulai jelajahi koleksi kami.",
                'status' => Blog::STATUS_PUBLISHED,
                'published_at' => now()->subDays(2),
            ],
            [
                'slug' => 'tips-membangun-kebiasaan-membaca-setiap-hari',
                'category' => 'tips-membaca',
                'title' => 'Tips Membangun Kebiasaan Membaca Setiap Hari',
                'excerpt' => 'Langkah sederhana agar membaca menjadi bagian dari rutinitas harian.',
                'content' => "Mulailah dengan target kecil, misalnya sepuluh halaman setiap hari.\n\n"
                    . "Pilih waktu yang konsisten, seperti sebelum tidur atau saat perjalanan, lalu jauhkan gawai selama membaca.\n\n"
                    . "Catat buku yang sudah selesai dibaca agar kemajuan Anda terlihat dan membuat semangat bertahan.",
                'status' => Blog::STATUS_PUBLISHED,
                'published_at' => now()->subDays(5),
            ],
            [
                'slug' => 'cara-meminjam-buku-secara-online',
                'category' => 'berita-perpustakaan',
                'title' => 'Cara Meminjam Buku Secara Online',
                'excerpt' => 'Panduan singkat mengajukan peminjaman lewat akun anggota.',
                'content' => "Masuk ke akun anggota, buka katalog, lalu pilih buku yang tersedia.\n\n"
                    . "Ajukan peminjaman dan tunggu persetujuan petugas. Setelah disetujui, ambil buku di perpustakaan dan perhatikan tanggal jatuh tempo.\n\n"
                    . "Kembalikan buku tepat waktu untuk menghindari denda keterlambatan.",
                'status' => Blog::STATUS_PUBLISHED,
                'published_at' => now()->subDays(9),
            ],
            [
                'slug' => 'rekomendasi-novel-indonesia-bulan-ini',
                'category' => 'resensi-buku',
                'title' => 'Rekomendasi Novel Indonesia Bulan Ini',
                'excerpt' => 'Draf artikel rekomendasi bacaan (belum dipublikasikan).',
                'content' => "Artikel ini masih berupa draf dan belum tampil di halaman publik.\n\n"
                    . "Gunakan untuk menguji alur draft, publish, dan unpublish pada modul blog.",
                'status' => Blog::STATUS_DRAFT,
                'published_at' => null,
            ],
        ];

        foreach ($posts as $post) {
            Blog::withTrashed()->updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'blog_category_id' => $categories[$post['category']],
                    'author_id' => $author->id,
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'status' => $post['status'],
                    'published_at' => $post['published_at'],
                ],
            );
        }
    }
}
