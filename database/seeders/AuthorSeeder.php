<?php

namespace Database\Seeders;

use App\Models\Author;
use Illuminate\Database\Seeder;

class AuthorSeeder extends Seeder
{
    public function run(): void
    {
        $authors = [
            ['Andrea Hirata', 'Penulis novel asal Belitung, dikenal lewat tetralogi Laskar Pelangi.'],
            ['Pramoedya Ananta Toer', 'Sastrawan Indonesia, penulis Tetralogi Buru.'],
            ['Ahmad Fuadi', 'Penulis trilogi Negeri 5 Menara.'],
            ['Henry Manampiring', 'Penulis yang memperkenalkan Stoisisme kepada pembaca Indonesia lewat Filosofi Teras.'],
            ['James Clear', 'Penulis dan pembicara tentang kebiasaan dan peningkatan diri.'],
            ['Yuval Noah Harari', 'Sejarawan dan penulis asal Israel.'],
            ['Robert C. Martin', 'Pengembang perangkat lunak dan penulis, dikenal dengan sebutan Uncle Bob.'],
            ['Eka Kurniawan', 'Novelis Indonesia, penulis Cantik Itu Luka.'],
            ['Tere Liye', 'Novelis Indonesia, penulis serial Bumi.'],
            ['B.J. Habibie', 'Presiden ketiga Republik Indonesia dan insinyur kedirgantaraan.'],
            ['Jostein Gaarder', 'Penulis asal Norwegia, penulis Dunia Sophie.'],
            ['Leila S. Chudori', 'Novelis dan jurnalis Indonesia.'],
            ['Dee Lestari', 'Penulis dan penyanyi Indonesia, penulis serial Supernova dan Perahu Kertas.'],
        ];

        foreach ($authors as [$name, $biography]) {
            Author::query()->updateOrCreate(['name' => $name], ['biography' => $biography]);
        }
    }
}
