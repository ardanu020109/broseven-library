<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Fiksi', 'fiksi', 'Novel, cerita pendek, dan karya sastra.'],
            ['Sains & Teknologi', 'sains-teknologi', 'Buku sains, pemrograman, dan teknologi.'],
            ['Sejarah', 'sejarah', 'Buku sejarah dan peradaban.'],
            ['Biografi', 'biografi', 'Kisah hidup tokoh dan inspirasi.'],
            ['Filsafat & Psikologi', 'filsafat-psikologi', 'Pemikiran, perilaku, dan pengembangan diri.'],
            ['Anak-Anak', 'anak-anak', 'Bacaan untuk anak dan remaja.'],
            ['Pendidikan', 'pendidikan', 'Buku pelajaran dan referensi akademik.'],
        ];

        foreach ($categories as [$name, $slug, $description]) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description],
            );
        }
    }
}
