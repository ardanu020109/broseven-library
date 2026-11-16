<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookStockHistory;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Shelf;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::query()->pluck('id', 'slug');
        $authors = Author::query()->pluck('id', 'name');
        $publishers = Publisher::query()->pluck('id', 'name');
        $shelves = Shelf::query()->pluck('id', 'code');

        // [judul, kategori, penulis, penerbit, rak, tahun, bahasa, halaman, stok, deskripsi]
        $books = [
            ['Laskar Pelangi', 'fiksi', 'Andrea Hirata', 'Bentang Pustaka', 'RK-A1', 2005, 'Indonesia', 529, 5, 'Novel tentang persahabatan sekelompok anak sekolah di Belitung.'],
            ['Sang Pemimpi', 'fiksi', 'Andrea Hirata', 'Bentang Pustaka', 'RK-A1', 2006, 'Indonesia', 292, 4, 'Lanjutan Laskar Pelangi tentang mimpi dan perjuangan meraih pendidikan.'],
            ['Bumi Manusia', 'fiksi', 'Pramoedya Ananta Toer', 'Lentera Dipantara', 'RK-A1', 1980, 'Indonesia', 535, 3, 'Novel sejarah berlatar masa kolonial, buku pertama Tetralogi Buru.'],
            ['Negeri 5 Menara', 'fiksi', 'Ahmad Fuadi', 'Gramedia Pustaka Utama', 'RK-A1', 2009, 'Indonesia', 423, 6, 'Kisah santri yang mengejar mimpi di sebuah pesantren.'],
            ['Filosofi Teras', 'filsafat-psikologi', 'Henry Manampiring', 'Penerbit Buku Kompas', 'RK-B1', 2018, 'Indonesia', 346, 5, 'Pengantar filosofi Stoa untuk menghadapi kehidupan sehari-hari.'],
            ['Atomic Habits', 'filsafat-psikologi', 'James Clear', 'Gramedia Pustaka Utama', 'RK-B1', 2019, 'Indonesia', 352, 8, 'Panduan praktis membangun kebiasaan baik dan meninggalkan kebiasaan buruk.'],
            ['Sapiens', 'sejarah', 'Yuval Noah Harari', 'Kepustakaan Populer Gramedia', 'RK-D1', 2017, 'Indonesia', 527, 4, 'Sejarah singkat umat manusia dari masa purba hingga modern.'],
            ['Clean Code', 'sains-teknologi', 'Robert C. Martin', 'Prentice Hall', 'RK-C1', 2008, 'Inggris', 464, 3, 'Panduan menulis kode program yang bersih dan mudah dirawat.'],
            ['Cantik Itu Luka', 'fiksi', 'Eka Kurniawan', 'Gramedia Pustaka Utama', 'RK-A1', 2002, 'Indonesia', 520, 3, 'Novel yang memadukan sejarah, mitos, dan realisme magis.'],
            ['Bumi', 'fiksi', 'Tere Liye', 'Gramedia Pustaka Utama', 'RK-A1', 2014, 'Indonesia', 440, 5, 'Novel fantasi petualangan, buku pertama serial Bumi.'],
            ['Habibie & Ainun', 'biografi', 'B.J. Habibie', 'THC Mandiri', 'RK-D1', 2010, 'Indonesia', 323, 4, 'Kisah perjalanan hidup dan cinta B.J. Habibie bersama Ainun.'],
            ['Dunia Sophie', 'filsafat-psikologi', 'Jostein Gaarder', 'Mizan', 'RK-B1', 1996, 'Indonesia', 798, 2, 'Novel yang memperkenalkan sejarah filsafat Barat.'],
            ['Laut Bercerita', 'fiksi', 'Leila S. Chudori', 'Kepustakaan Populer Gramedia', 'RK-A1', 2017, 'Indonesia', 379, 4, 'Novel tentang aktivis mahasiswa dan keluarga yang ditinggalkan.'],
            ['Perahu Kertas', 'fiksi', 'Dee Lestari', 'Bentang Pustaka', 'RK-A1', 2009, 'Indonesia', 444, 6, 'Novel tentang cinta, seni, dan mengejar impian.'],
        ];

        foreach ($books as $i => [$title, $category, $author, $publisher, $shelf, $year, $language, $pages, $stock, $description]) {
            $book = Book::withTrashed()->firstOrNew([
                'isbn' => sprintf('DEV-ISBN-%04d', $i + 1),
            ]);

            $isNew = ! $book->exists;

            // forceFill karena kolom stok sengaja tidak mass-assignable.
            $book->forceFill([
                'title' => $title,
                'category_id' => $categories[$category],
                'author_id' => $authors[$author],
                'publisher_id' => $publishers[$publisher],
                'shelf_id' => $shelves[$shelf],
                'shelf_number' => (string) (($i % 10) + 1),
                'publication_year' => $year,
                'language' => $language,
                'description' => $description,
                'pages' => $pages,
                'price' => 60000 + ($i * 5000),
                'status' => Book::STATUS_ACTIVE,
            ]);

            if ($isNew) {
                $book->forceFill([
                    'total_stock' => $stock,
                    'available_stock' => $stock,
                    'borrowed_stock' => 0,
                ]);
            }

            $book->save();

            if ($isNew) {
                BookStockHistory::query()->create([
                    'book_id' => $book->id,
                    'user_id' => null,
                    'type' => BookStockHistory::TYPE_INITIAL,
                    'quantity_change' => $stock,
                    'total_after' => $stock,
                    'available_after' => $stock,
                    'borrowed_after' => 0,
                    'notes' => 'Stok awal (seeder)',
                ]);
            }
        }
    }
}
