<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Book;
use App\Models\Borrowing;
use App\Services\BookStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class BorrowingApprovalTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeLibraryProfile(['loan_duration_days' => 10]);
    }

    /** @return array<string, array{string}> */
    public static function staffRoles(): array
    {
        return ['admin karyawan' => ['admin-karyawan'], 'super admin' => ['super-admin']];
    }

    /** @return array<string, array{string}> */
    public static function outsiders(): array
    {
        return ['admin master' => ['admin-master'], 'anggota' => ['anggota']];
    }

    // ------------------------------------------------------------------ persetujuan

    #[DataProvider('staffRoles')]
    public function test_approval_decreases_stock_sets_dates_and_logs_history(string $role): void
    {
        $staff = $this->makeStaff($role);
        $member = $this->makeMember();
        $a = Book::factory()->withStock(5, 5)->create();
        $b = Book::factory()->withStock(2, 2)->create();
        $borrowing = $this->makePending($member, [$a, $b]);

        $this->actingAs($staff)->post("/{$role}/borrowings/{$borrowing->id}/approve")
            ->assertRedirect("/{$role}/borrowings/{$borrowing->id}")
            ->assertSessionHas('success');

        $borrowing->refresh();
        $this->assertSame('borrowed', $borrowing->status->value);
        $this->assertSame($staff->id, $borrowing->processed_by);
        $this->assertNotNull($borrowing->approved_at);
        $this->assertTrue($borrowing->borrow_date->isToday());
        $this->assertTrue($borrowing->due_date->isSameDay(today()->addDays(10))); // dari profil perpustakaan

        $this->assertSame([5, 4, 1], $this->stockOf($a));
        $this->assertSame([2, 1, 1], $this->stockOf($b));

        $this->assertDatabaseHas('book_stock_histories', [
            'book_id' => $a->id, 'user_id' => $staff->id, 'type' => 'borrow', 'quantity_change' => -1,
            'available_after' => 4, 'borrowed_after' => 1,
            'reference_type' => Borrowing::class, 'reference_id' => $borrowing->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'action' => 'borrowing.approved']);
    }

    public function test_approval_is_all_or_nothing_when_one_book_is_short(): void
    {
        $staff = $this->makeStaff();
        $ok = Book::factory()->withStock(5, 5)->create();
        $short = Book::factory()->withStock(1, 1)->create();
        $borrowing = $this->makePending($this->makeMember(), [$ok, $short]);

        // Eksemplar terakhir buku kedua diambil pihak lain sebelum disetujui.
        $short->forceFill(['available_stock' => 0, 'borrowed_stock' => 1])->save();

        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$borrowing->id}/approve")->assertSessionHas('error');

        $this->assertSame('pending', $borrowing->fresh()->status->value);
        $this->assertSame([5, 5, 0], $this->stockOf($ok)); // buku pertama TIDAK berkurang
        $this->assertDatabaseMissing('book_stock_histories', ['type' => 'borrow']);
    }

    public function test_only_one_request_wins_the_last_copy(): void
    {
        $staff = $this->makeStaff();
        $last = Book::factory()->withStock(1, 1)->create();
        $first = $this->makePending($this->makeMember(), [$last]);
        $second = $this->makePending($this->makeMember(), [$last]);

        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$first->id}/approve")->assertSessionHas('success');
        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$second->id}/approve")->assertSessionHas('error');

        $this->assertSame('borrowed', $first->fresh()->status->value);
        $this->assertSame('pending', $second->fresh()->status->value);
        $this->assertSame([1, 0, 1], $this->stockOf($last)); // tidak minus
    }

    public function test_double_approval_does_not_decrease_stock_twice(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->withStock(5, 5)->create();
        $borrowing = $this->makePending($this->makeMember(), [$book]);

        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$borrowing->id}/approve")->assertSessionHas('success');
        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$borrowing->id}/approve")->assertSessionHas('error');

        $this->assertSame([5, 4, 1], $this->stockOf($book));
        $this->assertDatabaseCount('book_stock_histories', 1);
    }

    public function test_approval_is_refused_for_inactive_member_or_expired_membership(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->withStock(5, 5)->create();

        $inactive = $this->makeMember(['is_active' => false]);
        $one = $this->makePending($inactive, [$book]);

        $expired = $this->makeMember([], ['expired_at' => today()->subDay()->toDateString()]);
        $two = $this->makePending($expired, [$book]);

        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$one->id}/approve")->assertSessionHas('error');
        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$two->id}/approve")->assertSessionHas('error');

        $this->assertSame([5, 5, 0], $this->stockOf($book));
    }

    public function test_approval_is_refused_for_inactive_book(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->inactive()->withStock(5, 5)->create();
        $borrowing = $this->makePending($this->makeMember(), [$book]);

        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$borrowing->id}/approve")->assertSessionHas('error');

        $this->assertSame([5, 5, 0], $this->stockOf($book));
    }

    // ------------------------------------------------------------------ penolakan

    public function test_rejection_requires_a_reason_and_does_not_touch_stock(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->withStock(5, 5)->create();
        $borrowing = $this->makePending($this->makeMember(), [$book]);

        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$borrowing->id}/reject", [])->assertSessionHasErrors('reason');
        $this->assertSame('pending', $borrowing->fresh()->status->value);

        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$borrowing->id}/reject", ['reason' => 'Buku sedang dipesan'])
            ->assertSessionHas('success');

        $borrowing->refresh();
        $this->assertSame('rejected', $borrowing->status->value);
        $this->assertSame('Buku sedang dipesan', $borrowing->rejection_reason);
        $this->assertSame($staff->id, $borrowing->processed_by);
        $this->assertSame([5, 5, 0], $this->stockOf($book));
        $this->assertDatabaseHas('activity_logs', ['action' => 'borrowing.rejected']);
    }

    public function test_processed_requests_cannot_be_processed_again(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->withStock(5, 5)->create();
        $borrowing = $this->makePending($this->makeMember(), [$book], ['status' => 'rejected']);

        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$borrowing->id}/approve")->assertSessionHas('error');
        $this->actingAs($staff)->post("/admin-karyawan/borrowings/{$borrowing->id}/reject", ['reason' => 'x'])->assertSessionHas('error');

        $this->assertSame('rejected', $borrowing->fresh()->status->value);
        $this->assertSame([5, 5, 0], $this->stockOf($book));
    }

    // ------------------------------------------------------------------ hak akses

    #[DataProvider('outsiders')]
    public function test_other_roles_cannot_process_or_list_borrowings(string $role): void
    {
        $user = $this->makeStaff($role);
        $book = Book::factory()->withStock(5, 5)->create();
        $borrowing = $this->makePending($this->makeMember(), [$book]);

        $this->actingAs($user)->get('/admin-karyawan/borrowings')->assertForbidden();
        $this->actingAs($user)->get('/super-admin/borrowings')->assertForbidden();
        $this->actingAs($user)->post("/admin-karyawan/borrowings/{$borrowing->id}/approve")->assertForbidden();

        $this->assertSame('pending', $borrowing->fresh()->status->value);
        $this->assertSame([5, 5, 0], $this->stockOf($book));
    }

    // ------------------------------------------------------------------ halaman petugas

    public function test_detail_page_warns_and_disables_approval_when_stock_is_short(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->outOfStock()->create(['title' => 'Buku Kosong']);
        $borrowing = $this->makePending($this->makeMember(), [$book]);

        $this->actingAs($staff)->get("/admin-karyawan/borrowings/{$borrowing->id}")
            ->assertOk()
            ->assertSee('belum bisa disetujui')
            ->assertSee('Buku Kosong')
            ->assertSee('disabled', false);
    }

    public function test_detail_page_previews_the_due_date(): void
    {
        $staff = $this->makeStaff();
        $borrowing = $this->makePending($this->makeMember(), [Book::factory()->withStock(3, 3)->create()]);

        $this->actingAs($staff)->get("/admin-karyawan/borrowings/{$borrowing->id}")
            ->assertSee(today()->addDays(10)->translatedFormat('d F Y'));
    }

    public function test_borrowing_list_search_status_and_date_filters(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember(['name' => 'Siti Pencari']);
        $other = $this->makeMember(['name' => 'Budi Lainnya']);

        $this->makePending($member, [Book::factory()->create(['title' => 'Judul Cari Saya'])], ['code' => 'PJM-AAA']);
        $this->makePending($other, [Book::factory()->create(['title' => 'Judul Lain'])], ['code' => 'PJM-BBB', 'status' => 'rejected']);
        $this->makeBorrowed($other, $staff, [Book::factory()->withStock(3, 2)->create()], [
            'code' => 'PJM-CCC', 'due_date' => today()->subDays(2)->toDateString(), 'status' => 'borrowed',
        ]);

        $this->actingAs($staff)->get('/admin-karyawan/borrowings?q=Siti')->assertSee('PJM-AAA')->assertDontSee('PJM-BBB');
        $this->actingAs($staff)->get('/admin-karyawan/borrowings?q=Judul Cari')->assertSee('PJM-AAA')->assertDontSee('PJM-BBB');
        $this->actingAs($staff)->get('/admin-karyawan/borrowings?q=PJM-BBB')->assertSee('PJM-BBB')->assertDontSee('PJM-AAA');
        $this->actingAs($staff)->get('/admin-karyawan/borrowings?status=pending')->assertSee('PJM-AAA')->assertDontSee('PJM-BBB');
        $this->actingAs($staff)->get('/admin-karyawan/borrowings?status=rejected')->assertSee('PJM-BBB')->assertDontSee('PJM-AAA');

        // "Terlambat" dihitung dari tanggal jatuh tempo walaupun status tersimpan masih borrowed.
        $this->actingAs($staff)->get('/admin-karyawan/borrowings?status=overdue')->assertSee('PJM-CCC')->assertDontSee('PJM-AAA');

        $this->actingAs($staff)->get('/admin-karyawan/borrowings?from=' . today()->addDay()->toDateString())
            ->assertDontSee('PJM-AAA');
        $this->actingAs($staff)->get('/admin-karyawan/borrowings?status=ngawur&from=bukan&to=2026-99-99')->assertOk();
    }

    public function test_borrowing_list_is_paginated(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember();
        Borrowing::factory()->count(11)->create(['member_id' => $member->id, 'status' => 'pending']);

        $this->actingAs($staff)->get('/admin-karyawan/borrowings')
            ->assertSee('page-link')
            ->assertViewHas('borrowings', fn ($p) => $p->count() === 10 && $p->total() === 11);
    }

    public function test_active_list_shows_only_outstanding_books_and_filters(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember(['name' => 'Peminjam Aktif']);

        $this->makeBorrowed($member, $staff, [Book::factory()->withStock(4, 2)->create(['title' => 'Masih Dipinjam'])], ['code' => 'PJM-ACT'], 2, 1);
        $this->makeBorrowed($member, $staff, [Book::factory()->withStock(4, 4)->create(['title' => 'Sudah Kembali'])], ['code' => 'PJM-DONE'], 1, 1);
        $this->makeBorrowed($member, $staff, [Book::factory()->withStock(4, 3)->create(['title' => 'Telat Banget'])], [
            'code' => 'PJM-LATE', 'due_date' => today()->subDays(3)->toDateString(),
        ]);
        $this->makePending($member, [Book::factory()->create(['title' => 'Belum Disetujui'])], ['code' => 'PJM-PEND']);

        $this->actingAs($staff)->get('/admin-karyawan/borrowings/active')
            ->assertOk()
            ->assertSee('Masih Dipinjam')
            ->assertSee('Telat Banget')
            ->assertDontSee('Sudah Kembali')
            ->assertDontSee('Belum Disetujui')
            ->assertViewHas('items', fn ($items) => $items->total() === 2);

        $this->actingAs($staff)->get('/admin-karyawan/borrowings/active?status=overdue')->assertSee('Telat Banget')->assertDontSee('Masih Dipinjam');
        $this->actingAs($staff)->get('/admin-karyawan/borrowings/active?q=Telat')->assertSee('Telat Banget')->assertDontSee('Masih Dipinjam');
        $this->actingAs($staff)->get('/admin-karyawan/borrowings/active?q=Peminjam Aktif')->assertSee('Masih Dipinjam');
    }

    public function test_members_list_and_detail_for_staff(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember(['name' => 'Anggota Terlihat']);
        $this->makeStaff('admin-master'); // bukan anggota

        $this->actingAs($staff)->get('/admin-karyawan/members')->assertOk()->assertSee('Anggota Terlihat');
        $this->actingAs($staff)->get('/admin-karyawan/members?q=Terlihat')->assertSee('Anggota Terlihat');
        $this->actingAs($staff)->get("/admin-karyawan/members/{$member->id}")->assertOk()->assertSee($member->email);

        // Pengguna non-anggota tidak bisa dibuka lewat halaman anggota.
        $this->actingAs($staff)->get("/admin-karyawan/members/{$staff->id}")->assertNotFound();
    }

    public function test_menu_items_for_staff_are_enabled(): void
    {
        $this->actingAs($this->makeStaff())->get('/admin-karyawan/dashboard')
            ->assertSee(route('admin-karyawan.borrowings.index'), false)
            ->assertSee(route('admin-karyawan.borrowings.active'), false)
            ->assertSee(route('admin-karyawan.members.index'), false);
    }

    // ------------------------------------------------------------------ overdue & stok

    public function test_overdue_command_marks_only_late_running_loans(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember();
        $book = fn () => Book::factory()->withStock(3, 2)->create();

        $late = $this->makeBorrowed($member, $staff, [$book()], ['due_date' => today()->subDay()->toDateString()]);
        $partial = $this->makeBorrowed($member, $staff, [$book()], ['status' => 'partially_returned', 'due_date' => today()->subDays(5)->toDateString()]);
        $onTime = $this->makeBorrowed($member, $staff, [$book()], ['due_date' => today()->toDateString()]);
        $returned = $this->makeBorrowed($member, $staff, [$book()], ['status' => 'returned', 'due_date' => today()->subDays(9)->toDateString()]);
        $pending = $this->makePending($member, [$book()]);

        $this->assertSame(0, Artisan::call('library:mark-overdue'));
        $this->assertStringContainsString('2 peminjaman', Artisan::output());

        $this->assertSame('overdue', $late->fresh()->status->value);
        $this->assertSame('overdue', $partial->fresh()->status->value);
        $this->assertSame('borrowed', $onTime->fresh()->status->value);
        $this->assertSame('returned', $returned->fresh()->status->value);
        $this->assertSame('pending', $pending->fresh()->status->value);
    }

    public function test_stock_service_never_lets_stock_go_negative(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->withStock(2, 0)->create();
        $borrowing = $this->makePending($this->makeMember(), [$book]);

        try {
            app(BookStockService::class)->lend($book, 1, $staff, $borrowing);
            $this->fail('Seharusnya melempar BusinessRuleException.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->getMessage());
        }

        $this->assertSame([2, 0, 2], $this->stockOf($book));
        $this->assertDatabaseCount('book_stock_histories', 0);
    }
}
