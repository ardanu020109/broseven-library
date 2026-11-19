<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\LibraryProfileSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class InstallLibrary extends Command
{
    protected $signature = 'library:install {--name=} {--username=} {--email=} {--password=}';

    protected $description = 'Membuat Super Admin pertama dan data awal perpustakaan (aman dijalankan di production)';

    public function handle(): int
    {
        if (User::withTrashed()->where('role', Role::SuperAdmin->value)->exists()) {
            $this->error('Super Admin sudah ada. Instalasi dibatalkan agar tidak menimpa data.');

            return self::FAILURE;
        }

        $passwordGiven = filled($this->option('password'));

        $data = [
            'name' => $this->option('name') ?: $this->ask('Nama lengkap Super Admin'),
            'username' => Str::lower((string) ($this->option('username') ?: $this->ask('Username'))),
            'email' => Str::lower((string) ($this->option('email') ?: $this->ask('Email'))),
            'password' => $passwordGiven
                ? $this->option('password')
                : $this->secret('Password (minimal 8 karakter, huruf besar, huruf kecil, angka)'),
        ];
        $data['password_confirmation'] = $passwordGiven ? $data['password'] : $this->secret('Ulangi password');

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:' . User::USERNAME_PATTERN, Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($data) {
            $user = new User(Arr::only($data, ['name', 'username', 'email', 'password']));
            $user->role = Role::SuperAdmin;
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();

            // Seeder ini idempoten: aman dijalankan ulang.
            app(LibraryProfileSeeder::class)->run();
            app(SettingSeeder::class)->run();

            ActivityLog::record('system.installed', 'Instalasi awal: Super Admin pertama dibuat', $user, [], $user->id);
        });

        $this->info('Instalasi selesai. Super Admin "' . $data['username'] . '" berhasil dibuat.');
        $this->line('Langkah berikutnya: login, lalu lengkapi Profil Perpustakaan (nama, alamat, logo, aturan peminjaman).');

        return self::SUCCESS;
    }
}
