<?php

namespace Database\Seeders;

use App\Models\Publisher;
use Illuminate\Database\Seeder;

class PublisherSeeder extends Seeder
{
    public function run(): void
    {
        $publishers = [
            ['Bentang Pustaka', 'Yogyakarta'],
            ['Lentera Dipantara', 'Jakarta'],
            ['Gramedia Pustaka Utama', 'Jakarta'],
            ['Penerbit Buku Kompas', 'Jakarta'],
            ['Kepustakaan Populer Gramedia', 'Jakarta'],
            ['Prentice Hall', 'Amerika Serikat'],
            ['THC Mandiri', 'Jakarta'],
            ['Mizan', 'Bandung'],
        ];

        foreach ($publishers as [$name, $address]) {
            Publisher::query()->updateOrCreate(['name' => $name], ['address' => $address]);
        }
    }
}
