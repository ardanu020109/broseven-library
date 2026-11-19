<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\LibraryProfile;
use App\Models\ReturnItem;
use App\Models\ReturnTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class ReturnProcessingTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeLibraryProfile(['fine_per_day' => 2000]);
    }

    /** @return array<string, array{string}> */
    public static function outsiders(): array
    {
        return ['admin master' => ['admin-master'], 'anggota' => ['anggota']];
    }

    /** @return array<int, int> [borrowing_item_id => jumlah] untuk semua item sebuah peminjaman */
    private function allItems(Borrowing $borrowing, int $quantity = 1): array
    {
        return $borrowing->items->mapWithKeys(fn ($i) => [$i->id => $quantity])->all();
    }

    // ------------------------------------------------------------------ pengembalian tepat waktu

    public function test_on_time_full_return_restores_stock_and_closes_the_loan(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->withStock(5, 4)->create();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [$book], ['due_date' => today()->addDays(2)->toDateString()]);
        $item = $borrowing->items()->firstOrFail();

        $response = $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$item->id => 1], 'notes' => 'Kondisi baik']);

        $return = ReturnTransaction::firstOrFail();
        $response->assertRedirect("/admin-karyawan/returns/{$return->id}")->assertSessionHas('success');

        $this->assertMatchesRegularExpression('/^KMB-\d{8}-[A-Z0-9]{5}$/', $return->code);
        $this->assertSame('Kondisi baik', $return->notes);
        $this->assertSame($staff->id, $return->processed_by);
        $this->assertTrue($return->return_date->isToday());

        $this->assertSame('returned', $borrowing->fresh()->status->value);
        $this->assertSame([5, 5, 0], $this->stockOf($book));

        $item->refresh();
        $this->assertSame(1, $item->returned_quantity);
        $this->assertSame('returned', $item->return_status);

        $returnItem = ReturnItem::firstOrFail();
        $this->assertSame(0, $returnItem->days_late);
        $this->assertSame('0.00', $returnItem->fine_amount);
        $this->assertDatabaseCount('fines', 0);

        $this->assertDatabaseHas('book_stock_histories', [
            'book_id' => $book->id, 'user_id' => $staff->id, 'type' => 'return', 'quantity_change' => 1,
            'available_after' => 5, 'borrowed_after' => 0,
            'reference_type' => ReturnTransaction::class, 'reference_id' => $return->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'action' => 'return.processed']);
    }

    public function test_return_on_the_due_date_itself_has_no_fine(): void
    {
        $staff = $this->makeStaff();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [Book::factory()->withStock(3, 2)->create()], ['due_date' => today()->toDateString()]);

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => $this->allItems($borrowing)]);

        $this->assertDatabaseCount('fines', 0);
    }

    // ------------------------------------------------------------------ denda

    public function test_late_return_creates_fine_with_snapshot_that_survives_rate_changes(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember();
        $borrowing = $this->makeBorrowed($member, $staff, [Book::factory()->withStock(3, 2)->create()], ['due_date' => today()->subDays(3)->toDateString()]);

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => $this->allItems($borrowing)]);

        $returnItem = ReturnItem::firstOrFail();
        $this->assertSame(3, $returnItem->days_late);
        $this->assertSame('2000.00', $returnItem->fine_per_day);
        $this->assertSame('6000.00', $returnItem->fine_amount);
        $this->assertTrue($returnItem->due_date->isSameDay(today()->subDays(3)));

        $fine = Fine::firstOrFail();
        $this->assertSame($member->id, $fine->member_id);
        $this->assertSame($returnItem->id, $fine->return_item_id);
        $this->assertSame('6000.00', $fine->amount);
        $this->assertSame('0.00', $fine->paid_amount);
        $this->assertSame('unpaid', $fine->status->value);

        // Tarif diubah setelahnya: riwayat lama tidak berubah.
        LibraryProfile::firstOrFail()->update(['fine_per_day' => 9999]);

        $this->assertSame('6000.00', $returnItem->fresh()->fine_amount);
        $this->assertSame('2000.00', $returnItem->fresh()->fine_per_day);
        $this->assertSame('6000.00', $fine->fresh()->amount);
    }

    public function test_overdue_status_loans_can_be_returned(): void
    {
        $staff = $this->makeStaff();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [Book::factory()->withStock(3, 2)->create()], [
            'status' => 'overdue', 'due_date' => today()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => $this->allItems($borrowing)]);

        $this->assertSame('returned', $borrowing->fresh()->status->value);
        $this->assertSame('10000.00', Fine::firstOrFail()->amount);
    }

    public function test_return_page_previews_the_fine(): void
    {
        $staff = $this->makeStaff();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [Book::factory()->withStock(3, 2)->create(['title' => 'Buku Pratinjau'])], [
            'due_date' => today()->subDays(2)->toDateString(),
        ]);

        $this->actingAs($staff)->get("/admin-karyawan/returns/create/{$borrowing->id}")
            ->assertOk()
            ->assertSee('Buku Pratinjau')
            ->assertSee('Rp4.000')
            ->assertSee('Terlambat 2 hari');
    }

    // ------------------------------------------------------------------ sebagian

    public function test_partial_return_then_final_return(): void
    {
        $staff = $this->makeStaff();
        $a = Book::factory()->withStock(3, 2)->create();
        $b = Book::factory()->withStock(3, 2)->create();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [$a, $b]);
        [$itemA, $itemB] = $borrowing->items()->orderBy('id')->get()->all();

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$itemA->id => 1, $itemB->id => 0]]);

        $this->assertSame('partially_returned', $borrowing->fresh()->status->value);
        $this->assertSame([3, 3, 0], $this->stockOf($a));
        $this->assertSame([3, 2, 1], $this->stockOf($b));
        $this->assertSame('returned', $itemA->fresh()->return_status);
        $this->assertSame('not_returned', $itemB->fresh()->return_status);

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$itemB->id => 1]]);

        $this->assertSame('returned', $borrowing->fresh()->status->value);
        $this->assertSame([3, 3, 0], $this->stockOf($b));
        $this->assertDatabaseCount('returns', 2);
    }

    public function test_item_with_several_copies_can_be_returned_in_parts_and_each_part_is_fined(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->withStock(5, 3)->create(); // 2 eksemplar dipinjam
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [$book], ['due_date' => today()->subDays(2)->toDateString()], 2);
        $item = $borrowing->items()->firstOrFail();

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$item->id => 1]]);

        $item->refresh();
        $this->assertSame(1, $item->returned_quantity);
        $this->assertSame('partially_returned', $item->return_status);
        $this->assertSame('partially_returned', $borrowing->fresh()->status->value);
        $this->assertSame([5, 4, 1], $this->stockOf($book));
        $this->assertSame('4000.00', Fine::firstOrFail()->amount); // 2 hari x Rp2.000 x 1 eksemplar

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$item->id => 1]]);

        $this->assertSame('returned', $borrowing->fresh()->status->value);
        $this->assertSame([5, 5, 0], $this->stockOf($book));
        $this->assertDatabaseCount('fines', 2);
    }

    // ------------------------------------------------------------------ pengembalian ganda & validasi

    public function test_same_item_cannot_be_returned_twice(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->withStock(5, 4)->create();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [$book]);
        $item = $borrowing->items()->firstOrFail();

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$item->id => 1]])->assertSessionHas('success');
        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$item->id => 1]])->assertSessionHas('error');

        $this->assertSame([5, 5, 0], $this->stockOf($book)); // stok hanya bertambah sekali
        $this->assertDatabaseCount('returns', 1);
        $this->assertDatabaseCount('book_stock_histories', 1);
    }

    public function test_quantity_above_outstanding_is_refused_without_changes(): void
    {
        $staff = $this->makeStaff();
        $book = Book::factory()->withStock(5, 4)->create();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [$book]);
        $item = $borrowing->items()->firstOrFail();

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$item->id => 2]])->assertSessionHas('error');

        $this->assertSame([5, 4, 1], $this->stockOf($book));
        $this->assertDatabaseCount('returns', 0);
        $this->assertSame('borrowed', $borrowing->fresh()->status->value);
    }

    public function test_empty_selection_and_foreign_items_are_refused(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember();
        $mine = $this->makeBorrowed($member, $staff, [Book::factory()->withStock(3, 2)->create()]);
        $other = $this->makeBorrowed($member, $staff, [Book::factory()->withStock(3, 2)->create()]);
        $itemMine = $mine->items()->firstOrFail();
        $itemOther = $other->items()->firstOrFail();

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$mine->id}", [])->assertSessionHasErrors('items');
        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$mine->id}", ['items' => [$itemMine->id => 0]])->assertSessionHas('error');
        // Item milik peminjaman lain tidak boleh ikut diproses.
        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$mine->id}", ['items' => [$itemOther->id => 1]])->assertSessionHas('error');

        $this->assertDatabaseCount('returns', 0);
        $this->assertSame(0, $itemOther->fresh()->returned_quantity);
    }

    public function test_loans_that_are_not_running_cannot_be_returned(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember();
        $book = Book::factory()->withStock(3, 3)->create();
        $pending = $this->makePending($member, [$book]);
        $item = $pending->items()->firstOrFail();

        $this->actingAs($staff)->get("/admin-karyawan/returns/create/{$pending->id}")->assertRedirect()->assertSessionHas('error');
        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$pending->id}", ['items' => [$item->id => 1]])->assertSessionHas('error');

        $this->assertSame([3, 3, 0], $this->stockOf($book));
    }

    public function test_everything_is_rolled_back_when_stock_data_is_inconsistent(): void
    {
        $staff = $this->makeStaff();
        // Data rusak: peminjaman aktif tetapi stok dipinjam di buku 0.
        $book = Book::factory()->withStock(5, 5)->create();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [$book], ['due_date' => today()->subDays(2)->toDateString()]);
        $item = $borrowing->items()->firstOrFail();

        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$item->id => 1]])->assertSessionHas('error');

        $this->assertDatabaseCount('returns', 0);
        $this->assertDatabaseCount('return_items', 0);
        $this->assertDatabaseCount('fines', 0); // denda yang sempat dibuat ikut dibatalkan
        $this->assertSame(0, $item->fresh()->returned_quantity);
        $this->assertSame('borrowed', $borrowing->fresh()->status->value);
        $this->assertSame([5, 5, 0], $this->stockOf($book));
    }

    // ------------------------------------------------------------------ halaman

    public function test_return_search_lists_only_running_loans(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember(['name' => 'Siti Pencari']);
        $other = $this->makeMember(['name' => 'Budi Lain']);

        $this->makeBorrowed($member, $staff, [Book::factory()->withStock(3, 2)->create()], ['code' => 'PJM-RUN-1']);
        $this->makeBorrowed($other, $staff, [Book::factory()->withStock(3, 2)->create()], ['code' => 'PJM-RUN-2']);
        $this->makeBorrowed($member, $staff, [Book::factory()->withStock(3, 3)->create()], ['code' => 'PJM-DONE', 'status' => 'returned'], 1, 1);
        $this->makePending($member, [Book::factory()->create()], ['code' => 'PJM-PEND']);

        $this->actingAs($staff)->get('/admin-karyawan/returns')
            ->assertOk()
            ->assertSee('PJM-RUN-1')->assertSee('PJM-RUN-2')
            ->assertDontSee('PJM-DONE')->assertDontSee('PJM-PEND');

        $this->actingAs($staff)->get('/admin-karyawan/returns?q=PJM-RUN-2')->assertSee('PJM-RUN-2')->assertDontSee('PJM-RUN-1');
        $this->actingAs($staff)->get('/admin-karyawan/returns?q=Siti')->assertSee('PJM-RUN-1')->assertDontSee('PJM-RUN-2');
        $this->actingAs($staff)->get('/admin-karyawan/returns?q=' . $member->memberProfile->member_number)->assertSee('PJM-RUN-1')->assertDontSee('PJM-RUN-2');
    }

    public function test_return_detail_page_and_borrowing_history_show_the_return(): void
    {
        $staff = $this->makeStaff();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [Book::factory()->withStock(3, 2)->create(['title' => 'Buku Riwayat'])], [
            'due_date' => today()->subDays(2)->toDateString(),
        ]);
        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => $this->allItems($borrowing)]);
        $return = ReturnTransaction::firstOrFail();

        $this->actingAs($staff)->get("/admin-karyawan/returns/{$return->id}")
            ->assertOk()->assertSee($return->code)->assertSee('Buku Riwayat')->assertSee('Rp4.000')->assertSee('Cetak Bukti');

        $this->actingAs($staff)->get("/admin-karyawan/borrowings/{$borrowing->id}")
            ->assertOk()->assertSee('Riwayat Pengembalian')->assertSee($return->code);

        $this->actingAs($staff)->get('/admin-karyawan/returns')->assertSee($return->code);
    }

    // ------------------------------------------------------------------ hak akses

    #[DataProvider('outsiders')]
    public function test_other_roles_cannot_process_returns(string $role): void
    {
        $user = $this->makeStaff($role);
        $staff = $this->makeStaff();
        $book = Book::factory()->withStock(3, 2)->create();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [$book]);
        $item = $borrowing->items()->firstOrFail();

        $this->actingAs($user)->get('/admin-karyawan/returns')->assertForbidden();
        $this->actingAs($user)->get('/super-admin/returns')->assertForbidden();
        $this->actingAs($user)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$item->id => 1]])->assertForbidden();

        $this->assertSame([3, 2, 1], $this->stockOf($book));
    }

    public function test_super_admin_can_process_returns_with_its_own_routes(): void
    {
        $admin = $this->makeStaff('super-admin');
        $borrowing = $this->makeBorrowed($this->makeMember(), $this->makeStaff(), [Book::factory()->withStock(3, 2)->create()]);

        $this->actingAs($admin)->post("/super-admin/returns/create/{$borrowing->id}", ['items' => $this->allItems($borrowing)])
            ->assertRedirect('/super-admin/returns/' . ReturnTransaction::firstOrFail()->id);

        $this->assertSame('returned', $borrowing->fresh()->status->value);
    }


    public function test_menu_items_for_returns_and_fines_are_enabled(): void
    {
        $this->actingAs($this->makeStaff())->get('/admin-karyawan/dashboard')
            ->assertSee(route('admin-karyawan.returns.index'), false)
            ->assertSee(route('admin-karyawan.fines.index'), false);

        $this->actingAs($this->makeStaff('super-admin'))->get('/super-admin/dashboard')
            ->assertSee(route('super-admin.returns.index'), false)
            ->assertSee(route('super-admin.fines.index'), false);

        $this->actingAs($this->makeMember())->get('/anggota/dashboard')
            ->assertSee(route('anggota.fines.index'), false);
    }
}
