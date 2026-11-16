<?php

namespace Database\Seeders;

use App\Models\Shelf;
use Illuminate\Database\Seeder;

class ShelfSeeder extends Seeder
{
    public function run(): void
    {
        $shelves = [
            ['RK-A1', 'Fiksi & Sastra', 'Lantai 1, sayap kiri', 'Novel dan karya sastra.'],
            ['RK-B1', 'Filsafat & Psikologi', 'Lantai 1, sayap kanan', 'Pemikiran dan pengembangan diri.'],
            ['RK-C1', 'Sains & Teknologi', 'Lantai 2, sayap kiri', 'Sains, pemrograman, dan teknologi.'],
            ['RK-D1', 'Sejarah & Biografi', 'Lantai 2, sayap kanan', 'Sejarah dan kisah tokoh.'],
            ['RK-E1', 'Anak & Pendidikan', 'Lantai 1, area baca anak', 'Bacaan anak dan buku pelajaran.'],
        ];

        foreach ($shelves as [$code, $name, $location, $description]) {
            Shelf::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'location' => $location, 'description' => $description],
            );
        }
    }
}
