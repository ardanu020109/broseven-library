<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class BorrowingRequestTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeLibraryProfile(['max_books_per_loan' => 2]);
    }

    /** @return array<string, array{string}> */
    public static function nonMembers(): array
    {
        return ['super admin' => ['super-admin'], 'admin master' => ['admin-master'], 'admin karyawan' => ['admin-karyawan']];
    }

    // ------------------------------------------------------------------ katalog

    public function test_member_catalog_lists_active_books_with_selection_controls(): void
    {
        $member = $this->makeMember();
        Book::factory()->withStock(5, 5)->create(['title' => 'Buku Tersedia']);
        Book::factory()->outOfStock()->create(['title' => 'Buku Habis']);
        Book::factory()->inactive()->create(['title' => 'Buku Nonaktif']);

        $this->actingAs($member)->get('/anggota/catalog')
            ->assertOk()
            ->assertSee('Buku Tersedia')
            ->assertSee('Buku Habis')
            ->assertSee('Sedang dipinjam semua')
            ->assertDontSee('Buku Nonaktif')
            ->assertSee('Ajukan Peminjaman');
    }

    public function test_catalog_shows_why_member_cannot_borrow(): void
    {
        $member = $this->makeMember([], ['expired_at' => today()->subDay()->toDateString()]);
        Book::factory()->create();

        $this->actingAs($member)->get('/anggota/catalog')
            ->assertSee('Anda belum dapat mengajukan peminjaman')
            ->assertSee('kedaluwarsa');
    }

    public function test_member_book_detail_marks_books_already_requested(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create();
        $this->makePending($member, [$book]);

        $this->actingAs($member)->get("/anggota/catalog/{$book->id}")->assertOk()->assertSee('sudah meminjam atau mengajukan');
    }

    public function test_inactive_book_detail_is_404_for_members(): void
    {
        $book = Book::factory()->inactive()->create();

        $this->actingAs($this->makeMember())->get("/anggota/catalog/{$book->id}")->assertNotFound();
    }

    // ------------------------------------------------------------------ pengajuan

    public function test_request_creates_pending_borrowing_without_touching_stock(): void
    {
        $member = $this->makeMember();
        $a = Book::factory()->withStock(5, 5)->create();
        $b = Book::factory()->withStock(3, 3)->create();

        $response = $this->actingAs($member)->post(route('anggota.borrowings.store'), [
            'books' => [$a->id, $b->id],
            'notes' => 'Untuk tugas kuliah',
        ]);

        $borrowing = Borrowing::firstOrFail();
        $response->assertRedirect(route('anggota.borrowings.show', $borrowing));

        $this->assertSame('pending', $borrowing->status->value);
        $this->assertSame($member->id, $borrowing->member_id);
        $this->assertSame('Untuk tugas kuliah', $borrowing->notes);
        $this->assertNull($borrowing->due_date);
        $this->assertMatchesRegularExpression('/^PJM-\d{8}-[A-Z0-9]{5}$/', $borrowing->code);
        $this->assertSame(2, $borrowing->items()->count());

        // Stok TIDAK berkurang saat pengajuan.
        $this->assertSame([5, 5, 0], $this->stockOf($a));
        $this->assertSame([3, 3, 0], $this->stockOf($b));
        $this->assertDatabaseHas('activity_logs', ['user_id' => $member->id, 'action' => 'borrowing.requested']);
    }

    public function test_request_validation(): void
    {
        $member = $this->makeMember();
        $books = Book::factory()->count(3)->create();

        $this->actingAs($member)->post(route('anggota.borrowings.store'), [])->assertSessionHasErrors('books');
        $this->actingAs($member)->post(route('anggota.borrowings.store'), ['books' => [99999]])->assertSessionHasErrors('books.0');
        $this->actingAs($member)->post(route('anggota.borrowings.store'), ['books' => $books->pluck('id')->all()])
            ->assertSessionHasErrors('books'); // batas profil: 2 buku

        $this->assertDatabaseCount('borrowings', 0);
    }

    public function test_request_is_refused_for_unavailable_inactive_or_duplicate_books(): void
    {
        $member = $this->makeMember();
        $empty = Book::factory()->outOfStock()->create();
        $inactive = Book::factory()->inactive()->create();
        $taken = Book::factory()->withStock(3, 3)->create();
        $this->makePending($member, [$taken]);

        $this->actingAs($member)->post(route('anggota.borrowings.store'), ['books' => [$empty->id]])->assertSessionHas('error');
        $this->actingAs($member)->post(route('anggota.borrowings.store'), ['books' => [$inactive->id]])->assertSessionHas('error');
        $this->actingAs($member)->post(route('anggota.borrowings.store'), ['books' => [$taken->id]])->assertSessionHas('error');

        $this->assertDatabaseCount('borrowings', 1); // hanya pengajuan awal
    }

    public function test_request_is_refused_when_member_has_overdue_loan(): void
    {
        $member = $this->makeMember();
        $staff = $this->makeStaff();
        $old = Book::factory()->withStock(3, 2)->create();
        $new = Book::factory()->withStock(3, 3)->create();
        $this->makeBorrowed($member, $staff, [$old], ['due_date' => today()->subDays(2)->toDateString()]);

        $this->actingAs($member)->post(route('anggota.borrowings.store'), ['books' => [$new->id]])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('borrowings', 1);
    }

    public function test_request_is_refused_for_expired_or_suspended_membership(): void
    {
        $book = Book::factory()->create();

        $expired = $this->makeMember([], ['expired_at' => today()->subDay()->toDateString()]);
        $this->actingAs($expired)->post(route('anggota.borrowings.store'), ['books' => [$book->id]])->assertSessionHas('error');

        $suspended = $this->makeMember([], ['status' => 'suspended']);
        $this->actingAs($suspended)->post(route('anggota.borrowings.store'), ['books' => [$book->id]])->assertSessionHas('error');

        $noProfile = User::factory()->create();
        $this->actingAs($noProfile)->post(route('anggota.borrowings.store'), ['books' => [$book->id]])->assertSessionHas('error');

        $this->assertDatabaseCount('borrowings', 0);
    }

    // ------------------------------------------------------------------ batal

    public function test_member_can_cancel_own_pending_request(): void
    {
        $member = $this->makeMember();
        $borrowing = $this->makePending($member, [Book::factory()->create()]);

        $this->actingAs($member)->post(route('anggota.borrowings.cancel', $borrowing))
            ->assertRedirect(route('anggota.borrowings.history'))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $borrowing->fresh()->status->value);
        $this->assertDatabaseHas('activity_logs', ['action' => 'borrowing.cancelled']);
    }

    public function test_only_pending_requests_can_be_cancelled(): void
    {
        $member = $this->makeMember();
        $borrowing = $this->makeBorrowed($member, $this->makeStaff(), [Book::factory()->withStock(3, 2)->create()]);

        $this->actingAs($member)->post(route('anggota.borrowings.cancel', $borrowing))->assertSessionHas('error');

        $this->assertSame('borrowed', $borrowing->fresh()->status->value);
    }

    public function test_member_cannot_cancel_or_view_someone_elses_borrowing(): void
    {
        $owner = $this->makeMember();
        $other = $this->makeMember();
        $borrowing = $this->makePending($owner, [Book::factory()->create()]);

        $this->actingAs($other)->post(route('anggota.borrowings.cancel', $borrowing))->assertForbidden();
        $this->actingAs($other)->get(route('anggota.borrowings.show', $borrowing))->assertForbidden();

        $this->assertSame('pending', $borrowing->fresh()->status->value);
    }

    // ------------------------------------------------------------------ daftar

    public function test_member_lists_show_only_own_data_split_by_state(): void
    {
        $member = $this->makeMember();
        $other = $this->makeMember();
        $staff = $this->makeStaff();

        $this->makePending($member, [Book::factory()->create(['title' => 'Milik Saya Pending'])], ['code' => 'PJM-MINE-1']);
        $this->makeBorrowed($member, $staff, [Book::factory()->withStock(3, 2)->create(['title' => 'Milik Saya Dipinjam'])], ['code' => 'PJM-MINE-2']);
        $this->makePending($member, [Book::factory()->create()], ['code' => 'PJM-MINE-3', 'status' => 'rejected']);
        $this->makePending($other, [Book::factory()->create(['title' => 'Milik Orang Lain'])], ['code' => 'PJM-OTHER']);

        $this->actingAs($member)->get('/anggota/borrowings')
            ->assertSee('PJM-MINE-1')->assertSee('PJM-MINE-2')
            ->assertDontSee('PJM-MINE-3')->assertDontSee('PJM-OTHER');

        $this->actingAs($member)->get('/anggota/borrowings/history')
            ->assertSee('PJM-MINE-3')
            ->assertDontSee('PJM-MINE-1')->assertDontSee('PJM-OTHER');
    }

    public function test_rejection_reason_is_visible_to_the_member(): void
    {
        $member = $this->makeMember();
        $borrowing = $this->makePending($member, [Book::factory()->create()], ['status' => 'rejected', 'rejection_reason' => 'Buku sedang dipesan']);

        $this->actingAs($member)->get(route('anggota.borrowings.show', $borrowing))->assertSee('Buku sedang dipesan');
    }

    // ------------------------------------------------------------------ akses

    #[DataProvider('nonMembers')]
    public function test_non_members_cannot_use_member_borrowing_routes(string $role): void
    {
        $user = $this->makeStaff($role);
        $book = Book::factory()->create();

        $this->actingAs($user)->get('/anggota/catalog')->assertForbidden();
        $this->actingAs($user)->get('/anggota/borrowings')->assertForbidden();
        $this->actingAs($user)->post('/anggota/borrowings', ['books' => [$book->id]])->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/anggota/catalog')->assertRedirect(route('login'));
        $this->post('/anggota/borrowings', ['books' => [1]])->assertRedirect(route('login'));
    }

    public function test_member_menu_items_are_enabled(): void
    {
        $this->actingAs($this->makeMember())->get('/anggota/dashboard')
            ->assertSee(route('anggota.catalog.index'), false)
            ->assertSee(route('anggota.borrowings.index'), false)
            ->assertSee(route('anggota.borrowings.history'), false);
    }
}
