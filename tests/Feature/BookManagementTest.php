<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function managers(): array
    {
        return ['super admin' => ['super-admin'], 'admin master' => ['admin-master']];
    }

    /** @return array<string, array{string}> */
    public static function outsiders(): array
    {
        return ['admin karyawan' => ['admin-karyawan'], 'anggota' => ['anggota']];
    }

    private function admin(string $role = 'admin-master'): User
    {
        return User::factory()->role(Role::from($role))->create();
    }

    /** @param array<string, mixed> $override */
    private function payload(array $override = []): array
    {
        return array_merge([
            'isbn' => '9786020000999',
            'title' => 'Buku Uji Baru',
            'category_id' => Category::factory()->create()->id,
            'author_id' => Author::factory()->create()->id,
            'publisher_id' => Publisher::factory()->create()->id,
            'shelf_id' => Shelf::factory()->create()->id,
            'shelf_number' => '5',
            'publication_year' => 2020,
            'language' => 'Indonesia',
            'edition' => 'Cetakan 1',
            'description' => 'Deskripsi uji.',
            'pages' => 250,
            'price' => 75000,
            'total_stock' => 8,
            'status' => 'active',
        ], $override);
    }

    // ------------------------------------------------------------------ akses

    #[DataProvider('managers')]
    public function test_managers_can_open_book_pages(string $role): void
    {
        $user = $this->admin($role);
        $book = Book::factory()->create();

        $this->actingAs($user)->get("/{$role}/books")->assertOk()->assertSee('Data Buku');
        $this->actingAs($user)->get("/{$role}/books/create")->assertOk()->assertSee('Jumlah Stok Awal');
        $this->actingAs($user)->get("/{$role}/books/{$book->id}")->assertOk()->assertSee($book->title);
        $this->actingAs($user)->get("/{$role}/books/{$book->id}/edit")->assertOk()->assertSee('Total Stok');
    }

    #[DataProvider('outsiders')]
    public function test_other_roles_cannot_manage_books(string $role): void
    {
        $user = $this->admin($role);
        $book = Book::factory()->create();

        $this->actingAs($user)->get('/admin-master/books')->assertForbidden();
        $this->actingAs($user)->get('/super-admin/books')->assertForbidden();
        $this->actingAs($user)->post('/admin-master/books', ['title' => 'x'])->assertForbidden();
        $this->actingAs($user)->delete("/admin-master/books/{$book->id}")->assertForbidden();

        $this->assertNotSoftDeleted($book);
    }

    // ------------------------------------------------------------------ tambah

    public function test_book_is_created_with_cover_and_initial_stock_history(): void
    {
        Storage::fake('public');
        $user = $this->admin();

        $response = $this->actingAs($user)->post(
            route('admin-master.books.store'),
            $this->payload(['cover' => UploadedFile::fake()->image('cover.jpg', 300, 420)])
        );

        $book = Book::where('isbn', '9786020000999')->firstOrFail();
        $response->assertRedirect(route('admin-master.books.show', $book));

        $this->assertSame([8, 8, 0], [$book->total_stock, $book->available_stock, $book->borrowed_stock]);
        $this->assertStringStartsWith('covers/', $book->cover);
        Storage::disk('public')->assertExists($book->cover);

        $this->assertDatabaseHas('book_stock_histories', [
            'book_id' => $book->id, 'user_id' => $user->id, 'type' => 'initial',
            'quantity_change' => 8, 'total_after' => 8, 'available_after' => 8, 'borrowed_after' => 0,
        ]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'book.created']);
    }

    public function test_stock_columns_cannot_be_set_through_the_request(): void
    {
        $this->actingAs($this->admin())->post(
            route('admin-master.books.store'),
            $this->payload(['available_stock' => 999, 'borrowed_stock' => 5])
        );

        $book = Book::where('isbn', '9786020000999')->firstOrFail();
        $this->assertSame([8, 8, 0], [$book->total_stock, $book->available_stock, $book->borrowed_stock]);
    }

    public function test_super_admin_is_redirected_to_its_own_book_page(): void
    {
        $this->actingAs($this->admin('super-admin'))
            ->post(route('super-admin.books.store'), $this->payload())
            ->assertRedirect(route('super-admin.books.show', Book::where('isbn', '9786020000999')->firstOrFail()));
    }

    public function test_book_without_isbn_or_shelf_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin-master.books.store'), $this->payload(['isbn' => '', 'shelf_id' => '', 'price' => '']))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('books', ['title' => 'Buku Uji Baru', 'isbn' => null, 'shelf_id' => null, 'price' => null]);
    }

    // ------------------------------------------------------------------ validasi

    public function test_required_and_invalid_fields_are_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin-master.books.store'), [])
            ->assertSessionHasErrors(['title', 'category_id', 'author_id', 'publisher_id', 'language', 'total_stock', 'status']);

        $this->actingAs($this->admin())->post(route('admin-master.books.store'), $this->payload([
            'category_id' => 99999,
            'publication_year' => 3000,
            'pages' => 0,
            'price' => -5,
            'total_stock' => -1,
            'status' => 'rahasia',
            'isbn' => '!!',
            'cover' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors(['category_id', 'publication_year', 'pages', 'price', 'total_stock', 'status', 'isbn', 'cover']);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_isbn_must_be_unique_but_may_stay_the_same_on_update(): void
    {
        $user = $this->admin();
        Book::factory()->create(['isbn' => '9786020000001']);
        $mine = Book::factory()->create(['isbn' => '9786020000002']);

        $this->actingAs($user)->post(route('admin-master.books.store'), $this->payload(['isbn' => '9786020000001']))
            ->assertSessionHasErrors('isbn');

        $this->actingAs($user)->put(route('admin-master.books.update', $mine), $this->payload([
            'isbn' => '9786020000001', 'total_stock' => $mine->total_stock,
        ]))->assertSessionHasErrors('isbn');

        $this->actingAs($user)->put(route('admin-master.books.update', $mine), $this->payload([
            'isbn' => '9786020000002', 'total_stock' => $mine->total_stock,
        ]))->assertSessionDoesntHaveErrors();
    }

    // ------------------------------------------------------------------ ubah dan stok

    public function test_update_changes_book_data_and_logs_activity(): void
    {
        $user = $this->admin();
        $book = Book::factory()->withStock(5, 5)->create();

        $this->actingAs($user)->put(route('admin-master.books.update', $book), $this->payload([
            'isbn' => $book->isbn, 'title' => 'Judul Setelah Diubah', 'total_stock' => 5, 'status' => 'inactive',
        ]))->assertRedirect(route('admin-master.books.show', $book));

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => 'Judul Setelah Diubah', 'status' => 'inactive']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'book.updated']);
    }

    public function test_increasing_total_stock_keeps_borrowed_and_updates_available(): void
    {
        $user = $this->admin();
        $book = Book::factory()->withStock(10, 7)->create(); // dipinjam 3

        $this->actingAs($user)->put(route('admin-master.books.update', $book), $this->payload([
            'isbn' => $book->isbn, 'total_stock' => 15, 'stock_note' => 'Hibah',
        ]))->assertSessionDoesntHaveErrors();

        $book->refresh();
        $this->assertSame([15, 12, 3], [$book->total_stock, $book->available_stock, $book->borrowed_stock]);

        $this->assertDatabaseHas('book_stock_histories', [
            'book_id' => $book->id, 'user_id' => $user->id, 'type' => 'adjustment',
            'quantity_change' => 5, 'total_after' => 15, 'available_after' => 12, 'borrowed_after' => 3, 'notes' => 'Hibah',
        ]);
    }

    public function test_decreasing_total_stock_down_to_borrowed_is_allowed(): void
    {
        $book = Book::factory()->withStock(10, 7)->create(); // dipinjam 3

        $this->actingAs($this->admin())->put(route('admin-master.books.update', $book), $this->payload([
            'isbn' => $book->isbn, 'total_stock' => 3,
        ]))->assertSessionDoesntHaveErrors();

        $book->refresh();
        $this->assertSame([3, 0, 3], [$book->total_stock, $book->available_stock, $book->borrowed_stock]);
        $this->assertDatabaseHas('book_stock_histories', ['book_id' => $book->id, 'quantity_change' => -7]);
    }

    public function test_total_stock_below_borrowed_is_rejected_and_everything_is_rolled_back(): void
    {
        Storage::fake('public');
        $book = Book::factory()->withStock(10, 7)->create(['title' => 'Judul Asli']); // dipinjam 3

        $this->actingAs($this->admin())->put(route('admin-master.books.update', $book), $this->payload([
            'isbn' => $book->isbn,
            'title' => 'Judul Tidak Boleh Berubah',
            'total_stock' => 2,
            'cover' => UploadedFile::fake()->image('baru.jpg'),
        ]))->assertSessionHasErrors('total_stock');

        $book->refresh();
        $this->assertSame('Judul Asli', $book->title);
        $this->assertSame([10, 7, 3], [$book->total_stock, $book->available_stock, $book->borrowed_stock]);
        $this->assertDatabaseMissing('book_stock_histories', ['book_id' => $book->id, 'type' => 'adjustment']);
        $this->assertSame([], Storage::disk('public')->allFiles('covers'));
    }

    public function test_unchanged_total_does_not_write_stock_history(): void
    {
        $book = Book::factory()->withStock(10, 7)->create();

        $this->actingAs($this->admin())->put(route('admin-master.books.update', $book), $this->payload([
            'isbn' => $book->isbn, 'total_stock' => 10,
        ]))->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('book_stock_histories', ['book_id' => $book->id, 'type' => 'adjustment']);
    }

    public function test_cover_can_be_replaced_and_removed(): void
    {
        Storage::fake('public');
        $user = $this->admin();

        $this->actingAs($user)->post(route('admin-master.books.store'), $this->payload([
            'cover' => UploadedFile::fake()->image('satu.jpg'),
        ]));
        $book = Book::where('isbn', '9786020000999')->firstOrFail();
        $first = $book->cover;

        $this->actingAs($user)->put(route('admin-master.books.update', $book), $this->payload([
            'isbn' => $book->isbn, 'total_stock' => 8, 'cover' => UploadedFile::fake()->image('dua.png'),
        ]));
        $second = $book->fresh()->cover;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($user)->put(route('admin-master.books.update', $book), $this->payload([
            'isbn' => $book->isbn, 'total_stock' => 8, 'remove_cover' => '1',
        ]));
        $this->assertNull($book->fresh()->cover);
        Storage::disk('public')->assertMissing($second);
    }

    // ------------------------------------------------------------------ hapus

    public function test_book_with_borrowed_stock_cannot_be_deleted(): void
    {
        $book = Book::factory()->withStock(5, 3)->create();

        $this->actingAs($this->admin())->delete(route('admin-master.books.destroy', $book))
            ->assertRedirect(route('admin-master.books.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($book);
    }

    public function test_book_with_pending_request_cannot_be_deleted(): void
    {
        $book = Book::factory()->create();
        $borrowing = Borrowing::factory()->create(['status' => 'pending']);
        BorrowingItem::factory()->create(['borrowing_id' => $borrowing->id, 'book_id' => $book->id]);

        $this->actingAs($this->admin())->delete(route('admin-master.books.destroy', $book))->assertSessionHas('error');

        $this->assertNotSoftDeleted($book);
    }

    public function test_book_with_only_finished_transactions_is_soft_deleted_and_history_is_kept(): void
    {
        $book = Book::factory()->create(['title' => 'Buku Riwayat']);
        $borrowing = Borrowing::factory()->create(['status' => 'returned']);
        $item = BorrowingItem::factory()->create([
            'borrowing_id' => $borrowing->id, 'book_id' => $book->id, 'returned_quantity' => 1, 'return_status' => 'returned',
        ]);

        $this->actingAs($this->admin())->delete(route('admin-master.books.destroy', $book))
            ->assertRedirect(route('admin-master.books.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($book);
        $this->assertDatabaseHas('borrowing_items', ['id' => $item->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'book.deleted']);

        $this->get('/katalog/' . $book->id)->assertNotFound();
        $this->actingAs($this->admin())->get('/admin-master/books')->assertDontSee('Buku Riwayat');
    }

    // ------------------------------------------------------------------ pencarian, filter, pagination

    public function test_search_and_filters(): void
    {
        $user = $this->admin();
        $category = Category::factory()->create();
        $author = Author::factory()->create();
        $publisher = Publisher::factory()->create();

        Book::factory()->create(['title' => 'Alpha Pencarian', 'isbn' => '9786021000001']);
        Book::factory()->create(['title' => 'Beta Kategori', 'category_id' => $category->id]);
        Book::factory()->create(['title' => 'Gamma Penulis', 'author_id' => $author->id]);
        Book::factory()->create(['title' => 'Delta Penerbit', 'publisher_id' => $publisher->id]);
        Book::factory()->inactive()->create(['title' => 'Epsilon Nonaktif']);

        $this->actingAs($user)->get('/admin-master/books?q=Alpha')->assertSee('Alpha Pencarian')->assertDontSee('Beta Kategori');
        $this->actingAs($user)->get('/admin-master/books?q=9786021000001')->assertSee('Alpha Pencarian')->assertDontSee('Beta Kategori');
        $this->actingAs($user)->get('/admin-master/books?category=' . $category->id)->assertSee('Beta Kategori')->assertDontSee('Gamma Penulis');
        $this->actingAs($user)->get('/admin-master/books?author=' . $author->id)->assertSee('Gamma Penulis')->assertDontSee('Beta Kategori');
        $this->actingAs($user)->get('/admin-master/books?publisher=' . $publisher->id)->assertSee('Delta Penerbit')->assertDontSee('Gamma Penulis');
        $this->actingAs($user)->get('/admin-master/books?status=inactive')->assertSee('Epsilon Nonaktif')->assertDontSee('Alpha Pencarian');
        $this->actingAs($user)->get('/admin-master/books')->assertSee('Epsilon Nonaktif'); // admin melihat buku nonaktif
    }

    public function test_availability_filter(): void
    {
        $user = $this->admin();

        Book::factory()->withStock(10, 10)->create(['title' => 'Stok Banyak']);
        Book::factory()->withStock(10, 1)->create(['title' => 'Stok Tipis']);   // ambang bawaan: 2
        Book::factory()->outOfStock()->create(['title' => 'Stok Habis']);

        $this->actingAs($user)->get('/admin-master/books?availability=available')
            ->assertSee('Stok Banyak')->assertSee('Stok Tipis')->assertDontSee('Stok Habis');
        $this->actingAs($user)->get('/admin-master/books?availability=low')
            ->assertSee('Stok Tipis')->assertDontSee('Stok Banyak')->assertDontSee('Stok Habis');
        $this->actingAs($user)->get('/admin-master/books?availability=empty')
            ->assertSee('Stok Habis')->assertDontSee('Stok Banyak')->assertDontSee('Stok Tipis');
    }

    public function test_garbage_filters_do_not_break_the_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin-master/books?category=abc&author=-4&availability=x&status=y')
            ->assertOk();
    }

    public function test_book_list_is_paginated(): void
    {
        Book::factory()->count(11)->create();
        $user = $this->admin();

        $this->actingAs($user)->get('/admin-master/books')
            ->assertSee('page-link')
            ->assertViewHas('books', fn ($books) => $books->count() === 10 && $books->total() === 11);

        $this->actingAs($user)->get('/admin-master/books?page=2')
            ->assertViewHas('books', fn ($books) => $books->count() === 1);
    }

    // ------------------------------------------------------------------ detail dan menu

    public function test_detail_page_shows_stock_and_history(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->post(route('admin-master.books.store'), $this->payload(['title' => 'Detail Stok Uji']));
        $book = Book::where('title', 'Detail Stok Uji')->firstOrFail();

        $this->actingAs($user)->get(route('admin-master.books.show', $book))
            ->assertOk()
            ->assertSee('Detail Stok Uji')
            ->assertSee('Riwayat Stok')
            ->assertSee('Stok awal');
    }

    public function test_books_menu_is_enabled_in_sidebar(): void
    {
        $this->actingAs($this->admin())->get('/admin-master/dashboard')
            ->assertSee(route('admin-master.books.index'), false);

        $this->actingAs($this->admin('super-admin'))->get('/super-admin/dashboard')
            ->assertSee(route('super-admin.books.index'), false);
    }
}
