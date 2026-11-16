<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Data dasar yang aman di semua environment.
        $this->call([
            LibraryProfileSeeder::class,
            SettingSeeder::class,
        ]);

        // Akun development berpassword dikenal (Password123!) TIDAK boleh ada di production.
        if (app()->environment('production')) {
            $this->command?->warn('Environment production: akun dan data contoh tidak dibuat. Jalankan: php artisan library:install');

            return;
        }

        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            AuthorSeeder::class,
            PublisherSeeder::class,
            ShelfSeeder::class,
            BookSeeder::class,
            BlogSeeder::class,
        ]);
    }
}
