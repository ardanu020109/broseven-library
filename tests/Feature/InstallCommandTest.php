<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\LibraryProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_creates_the_first_super_admin_and_base_data(): void
    {
        $this->artisan('library:install', [
            '--name' => 'Pemilik Perpustakaan',
            '--username' => 'Pemilik',
            '--email' => 'Pemilik@Example.test',
            '--password' => 'Rahasia123',
        ])->assertExitCode(0);

        $user = User::where('username', 'pemilik')->firstOrFail();
        $this->assertSame(Role::SuperAdmin, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('pemilik@example.test', $user->email);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
        $this->assertSame(1, LibraryProfile::count());
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'system.installed']);
    }

    public function test_install_works_interactively(): void
    {
        $this->artisan('library:install')
            ->expectsQuestion('Nama lengkap Super Admin', 'Admin Interaktif')
            ->expectsQuestion('Username', 'interaktif')
            ->expectsQuestion('Email', 'interaktif@example.test')
            ->expectsQuestion('Password (minimal 8 karakter, huruf besar, huruf kecil, angka)', 'Rahasia123')
            ->expectsQuestion('Ulangi password', 'Rahasia123')
            ->assertExitCode(0);

        $this->assertSame(Role::SuperAdmin, User::where('username', 'interaktif')->firstOrFail()->role);
    }

    public function test_install_refuses_to_run_when_a_super_admin_already_exists(): void
    {
        User::factory()->superAdmin()->create();

        $this->artisan('library:install', ['--name' => 'X', '--username' => 'baru', '--email' => 'baru@example.test', '--password' => 'Rahasia123'])
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['username' => 'baru']);
    }

    public function test_install_rejects_weak_passwords_and_duplicate_accounts(): void
    {
        $this->artisan('library:install', ['--name' => 'X', '--username' => 'lemah', '--email' => 'lemah@example.test', '--password' => 'abc'])
            ->assertExitCode(1);
        $this->assertDatabaseMissing('users', ['username' => 'lemah']);

        User::factory()->create(['username' => 'dipakai', 'email' => 'dipakai@example.test']);
        $this->artisan('library:install', ['--name' => 'X', '--username' => 'dipakai', '--email' => 'lain@example.test', '--password' => 'Rahasia123'])
            ->assertExitCode(1);
    }

    public function test_seeding_in_production_creates_no_accounts(): void
    {
        $this->app['env'] = 'production';

        // db:seed menolak berjalan di production tanpa --force, jadi seeder dipanggil langsung.
        (new DatabaseSeeder())->setContainer($this->app)->run();

        $this->assertSame(0, User::count());
        $this->assertSame(1, LibraryProfile::count());
    }

    public function test_seeding_in_local_environment_creates_development_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(8, User::count());
        $this->assertTrue(User::where('username', 'superadmin')->exists());
    }
}
