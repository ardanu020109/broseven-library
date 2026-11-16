<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class FullLifecycleTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    public function test_complete_journey_from_registration_to_reports(): void
    {
        $this->makeLibraryProfile(['name' => 'Perpustakaan Uji', 'max_books_per_loan' => 3, 'loan_duration_days' => 7, 'fine_per_day' => 2000]);
        $staff = $this->makeStaff();
        $admin = $this->makeStaff('super-admin');
        $book = Book::factory()->withStock(2, 2)->create(['title' => 'Buku Siklus Hidup']);

        // 1. Calon anggota mendaftar sendiri.
        $this->post('/register', [
            'name' => 'Anggota Siklus', 'username' => 'siklus.anggota', 'email' => 'siklus@example.test',
            'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
        ])->assertRedirect(route('anggota.dashboard'));

        $member = User::where('username', 'siklus.anggota')->firstOrFail();
        $this->assertNotNull($member->memberProfile);
        auth()->logout();

        // 2. Mengajukan peminjaman: stok belum berubah.
        $this->actingAs($member)->post(route('anggota.borrowings.store'), ['books' => [$book->id]])->assertSessionHasNoErrors();
        $borrowing = Borrowing::firstOrFail();
        $this->assertSame('pending', $borrowing->status->value);
        $this->assertSame([2, 2, 0], $this->stockOf($book));

        // 3. Petugas menyetujui: stok berkurang, jatuh tempo 7 hari.
        $this->actingAs($staff)->post(route('admin-karyawan.borrowings.approve', $borrowing))->assertSessionHas('success');
        $borrowing->refresh();
        $this->assertSame('borrowed', $borrowing->status->value);
        $this->assertTrue($borrowing->due_date->isSameDay(today()->addDays(7)));
        $this->assertSame([2, 1, 1], $this->stockOf($book));

        // 4. Sepuluh hari kemudian: terlambat 3 hari, anggota tidak boleh meminjam lagi.
        $this->travelTo(now()->addDays(10));

        $this->actingAs($member)->get('/anggota/dashboard')->assertSee('melewati jatuh tempo');
        $other = Book::factory()->withStock(3, 3)->create();
        $this->actingAs($member)->post(route('anggota.borrowings.store'), ['books' => [$other->id]])->assertSessionHas('error');

        // 5. Pengembalian: stok pulih dan denda 3 hari x Rp2.000.
        $item = $borrowing->items()->firstOrFail();
        $this->actingAs($staff)->post(route('admin-karyawan.returns.store', $borrowing), ['items' => [$item->id => 1]])->assertSessionHas('success');

        $this->assertSame('returned', $borrowing->fresh()->status->value);
        $this->assertSame([2, 2, 0], $this->stockOf($book));

        $fine = Fine::firstOrFail();
        $this->assertSame('6000.00', $fine->amount);
        $this->assertSame('unpaid', $fine->status->value);

        // 6. Anggota melihat dendanya dan riwayat pengembalian.
        $this->actingAs($member)->get('/anggota/fines')->assertSee('Rp6.000')->assertSee('Belum Dibayar');
        $this->actingAs($member)->get(route('anggota.borrowings.show', $borrowing))->assertSee('Riwayat Pengembalian')->assertSee('KMB-');

        // 7. Petugas menerima pembayaran.
        $this->actingAs($staff)->post(route('admin-karyawan.fines.pay', $fine), ['amount' => 6000, 'method' => 'cash'])->assertSessionHas('success');
        $this->assertSame('paid', $fine->fresh()->status->value);
        $this->actingAs($member)->get('/anggota/fines')->assertSee('Lunas');

        // 8. Super Admin melihat laporan dan mengekspornya.
        $this->actingAs($admin)->get('/super-admin/reports/borrowings?period=all')
            ->assertOk()->assertSee('Buku Siklus Hidup')->assertSee('Dikembalikan')->assertSee('Anggota Siklus');
        $this->actingAs($admin)->get('/super-admin/reports/returns?period=all')
            ->assertSee('Rp6.000')->assertSee('Lunas');
        $this->actingAs($admin)->get('/super-admin/reports/returns/export/excel?period=all')->assertOk();
        $this->actingAs($admin)->get('/super-admin/reports/returns/export/pdf?period=all')->assertOk();

        // 9. Setelah semua beres, anggota bisa meminjam lagi.
        $this->actingAs($member)->post(route('anggota.borrowings.store'), ['books' => [$other->id]])->assertSessionHasNoErrors();
        $this->assertSame(2, Borrowing::count());

        // 10. Kartu anggota tetap dapat dicetak.
        $this->actingAs($member)->get('/anggota/membership-card/pdf')->assertOk();

        // 11. Seluruh langkah penting tercatat di audit log.
        foreach (['auth.register', 'borrowing.requested', 'borrowing.approved', 'return.processed', 'fine.paid', 'report.exported'] as $action) {
            $this->assertTrue(ActivityLog::where('action', $action)->exists(), "Audit log '{$action}' tidak ditemukan.");
        }
    }
}
