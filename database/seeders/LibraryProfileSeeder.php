<?php

namespace Database\Seeders;

use App\Models\LibraryProfile;
use Illuminate\Database\Seeder;

class LibraryProfileSeeder extends Seeder
{
    public function run(): void
    {
        LibraryProfile::query()->updateOrCreate(['id' => 1], [
            'name' => 'BroSeven Library',
            'tagline' => 'Jendela ilmu, teman setiap pembaca',
            'description' => 'BroSeven Library adalah perpustakaan modern yang menyediakan koleksi buku untuk semua kalangan. '
                . 'Kami berkomitmen menumbuhkan budaya membaca lewat layanan yang ramah, cepat, dan mudah diakses.',
            'address' => 'Jl. Contoh No. 7, Kota Contoh',
            'phone' => '0210000007',
            'email' => 'info@broseven.test',
            'website' => 'https://broseven.test',
            'head_name' => 'Kepala Perpustakaan',
            'operating_hours' => "Senin - Jumat: 08.00 - 16.00\nSabtu: 09.00 - 13.00\nMinggu & hari libur: Tutup",
            'borrowing_rules' => "1. Anggota wajib menunjukkan kartu anggota yang masih berlaku.\n"
                . "2. Buku wajib dikembalikan paling lambat pada tanggal jatuh tempo.\n"
                . "3. Keterlambatan dikenakan denda per hari per buku.\n"
                . "4. Buku yang rusak atau hilang wajib diganti.",
            'max_books_per_loan' => 3,
            'loan_duration_days' => 7,
            'fine_per_day' => 2000,
            'social_media' => [
                'instagram' => 'https://instagram.com/broseven.library',
                'facebook' => 'https://facebook.com/broseven.library',
                'youtube' => 'https://youtube.com/@broseven.library',
            ],
        ]);
    }
}
