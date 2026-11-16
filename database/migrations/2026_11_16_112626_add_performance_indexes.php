<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dashboard "peminjaman hari ini", filter tanggal pinjam pada daftar buku dipinjam.
        Schema::table('borrowings', function (Blueprint $table) {
            $table->index('borrow_date');
        });

        // Hitungan pengguna per role dan status aktif (dashboard, statistik beranda).
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'is_active']);
        });

        // Aktivitas per pengguna (detail pengguna, filter log) dan urutan waktu.
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        // Daftar denda diurutkan menurut waktu dibuat; riwayat pembayaran menurut waktu bayar.
        Schema::table('fines', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('fine_payments', function (Blueprint $table) {
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('fine_payments', fn (Blueprint $table) => $table->dropIndex(['paid_at']));
        Schema::table('fines', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('activity_logs', fn (Blueprint $table) => $table->dropIndex(['user_id', 'created_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['role', 'is_active']));
        Schema::table('borrowings', fn (Blueprint $table) => $table->dropIndex(['borrow_date']));
    }
};
