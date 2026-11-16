<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\LibraryProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Batas umum: 300 permintaan per menit per pengguna (atau per IP untuk tamu).
        RateLimiter::for('web', fn(Request $request) => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));
        // Pagination memakai Bootstrap 5 (bawaan Laravel 13 memakai Tailwind).
        Paginator::useBootstrapFive();

        // Nama bulan dan hari dalam bahasa Indonesia.
        Carbon::setLocale(config('app.locale'));

        $this->registerGates();

        // Profil perpustakaan tersedia di SEMUA view. Di-query sekali per request.
        View::composer('*', function ($view) {
            $request = request();

            if (! $request->attributes->has('library_profile')) {
                $request->attributes->set('library_profile', $this->resolveLibraryProfile());
            }

            $view->with('libraryProfile', $request->attributes->get('library_profile'));
        });
    }

    private function registerGates(): void
    {
        // Master data buku, kategori, penulis, penerbit, rak.
        Gate::define('manage-master-data', fn(User $user) => $user->hasRole(Role::SuperAdmin, Role::AdminMaster));

        // Pengguna, profil perpustakaan, blog, log aktivitas.
        Gate::define('manage-users', fn(User $user) => $user->hasRole(Role::SuperAdmin));
        Gate::define('manage-library', fn(User $user) => $user->hasRole(Role::SuperAdmin));
        // Memproses peminjaman, pengembalian, dan denda; melihat data anggota.
        Gate::define('manage-transactions', fn(User $user) => $user->hasRole(Role::SuperAdmin, Role::AdminKaryawan));
        // Pembebasan denda hanya oleh Super Admin. Pembayaran boleh oleh petugas layanan (manage-transactions).
        Gate::define('waive-fines', fn(User $user) => $user->hasRole(Role::SuperAdmin));
        // Kartu anggota: hanya pemilik kartu (anggota) atau petugas layanan yang berwenang.
        Gate::define('view-member-card', fn(User $viewer, User $owner) => $owner->role === Role::Anggota
            && ($viewer->is($owner) || $viewer->hasRole(Role::SuperAdmin, Role::AdminKaryawan)));
        // Laporan dan exportnya: Super Admin.
        Gate::define('view-reports', fn(User $user) => $user->hasRole(Role::SuperAdmin));
    }

    private function resolveLibraryProfile(): LibraryProfile
    {
        try {
            return LibraryProfile::current();
        } catch (Throwable) {
            // Database belum siap: tetap tampilkan halaman dengan nilai bawaan.
            return new LibraryProfile(['name' => config('app.name')]);
        }
    }
}
