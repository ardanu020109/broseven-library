<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCES = ['categories', 'authors', 'publishers', 'shelves'];

    /** @return array<string, array{string, string}> */
    public static function managers(): array
    {
        $cases = [];

        foreach (['super-admin', 'admin-master'] as $role) {
            foreach (self::RESOURCES as $resource) {
                $cases["{$role} {$resource}"] = [$role, $resource];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string, string}> */
    public static function outsiders(): array
    {
        $cases = [];

        foreach (['admin-karyawan', 'anggota'] as $role) {
            foreach (self::RESOURCES as $resource) {
                $cases["{$role} {$resource}"] = [$role, $resource];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string, array<string, string>}> */
    public static function payloads(): array
    {
        return [
            'categories' => ['categories', ['name' => 'Kategori Uji', 'description' => 'Deskripsi uji']],
            'authors' => ['authors', ['name' => 'Penulis Uji', 'biography' => 'Biografi uji']],
            'publishers' => ['publishers', ['name' => 'Penerbit Uji', 'phone' => '021-555-0100', 'email' => 'redaksi@uji.test']],
            'shelves' => ['shelves', ['code' => 'rk-z9', 'name' => 'Rak Uji', 'location' => 'Lantai 1']],
        ];
    }

    /** @return array<string, array{string, class-string, string}> */
    public static function usedByBooks(): array
    {
        return [
            'categories' => ['categories', Category::class, 'category_id'],
            'authors' => ['authors', Author::class, 'author_id'],
            'publishers' => ['publishers', Publisher::class, 'publisher_id'],
            'shelves' => ['shelves', Shelf::class, 'shelf_id'],
        ];
    }

    private function table(string $resource): string
    {
        return $resource;
    }

    // ------------------------------------------------------------------ akses

    #[DataProvider('managers')]
    public function test_super_admin_and_admin_master_can_open_pages(string $role, string $resource): void
    {
        $user = User::factory()->role(Role::from($role))->create();

        $this->actingAs($user)->get("/{$role}/{$resource}")->assertOk()->assertSee('Data ');
        $this->actingAs($user)->get("/{$role}/{$resource}/create")->assertOk()->assertSee('Simpan');
    }

    #[DataProvider('outsiders')]
    public function test_other_roles_cannot_access_master_data(string $role, string $resource): void
    {
        $user = User::factory()->role(Role::from($role))->create();

        $this->actingAs($user)->get("/admin-master/{$resource}")->assertForbidden();
        $this->actingAs($user)->get("/super-admin/{$resource}")->assertForbidden();
        $this->actingAs($user)->post("/admin-master/{$resource}", ['name' => 'x'])->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin-master/categories')->assertRedirect(route('login'));
    }

    public function test_admin_master_cannot_use_super_admin_routes(): void
    {
        $this->actingAs(User::factory()->adminMaster()->create())
            ->get('/super-admin/categories')
            ->assertForbidden();
    }

    // ------------------------------------------------------------------ CRUD

    /** @param array<string, string> $payload */
    #[DataProvider('payloads')]
    public function test_full_crud_flow_and_audit_log(string $resource, array $payload): void
    {
        $user = User::factory()->adminMaster()->create();
        $index = route("admin-master.{$resource}.index");

        // Tambah
        $this->actingAs($user)->post(route("admin-master.{$resource}.store"), $payload)->assertRedirect($index);
        $row = DB::table($this->table($resource))->where('name', $payload['name'])->first();
        $this->assertNotNull($row);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'master.created']);

        // Detail
        $this->actingAs($user)->get(route("admin-master.{$resource}.show", $row->id))->assertOk()->assertSee($payload['name']);

        // Ubah
        $this->actingAs($user)
            ->put(route("admin-master.{$resource}.update", $row->id), array_merge($payload, ['name' => 'Nama Diubah']))
            ->assertRedirect($index);
        $this->assertDatabaseHas($this->table($resource), ['id' => $row->id, 'name' => 'Nama Diubah']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'master.updated']);

        // Hapus
        $this->actingAs($user)->delete(route("admin-master.{$resource}.destroy", $row->id))->assertRedirect($index);
        $this->assertDatabaseMissing($this->table($resource), ['id' => $row->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'master.deleted']);
    }

    public function test_super_admin_can_create_with_its_own_routes_and_is_redirected_there(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('super-admin.categories.store'), ['name' => 'Dari Super Admin'])
            ->assertRedirect(route('super-admin.categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Dari Super Admin', 'slug' => 'dari-super-admin']);
    }

    // ------------------------------------------------------------------ validasi

    public function test_required_fields_are_validated(): void
    {
        $user = User::factory()->adminMaster()->create();

        $this->actingAs($user)->post(route('admin-master.categories.store'), [])->assertSessionHasErrors(['name', 'slug']);
        $this->actingAs($user)->post(route('admin-master.authors.store'), [])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('admin-master.publishers.store'), [])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('admin-master.shelves.store'), [])->assertSessionHasErrors(['code', 'name']);
    }

    public function test_category_slug_is_generated_and_must_be_unique(): void
    {
        $user = User::factory()->adminMaster()->create();

        $this->actingAs($user)->post(route('admin-master.categories.store'), ['name' => 'Sains & Teknologi']);
        $this->assertDatabaseHas('categories', ['slug' => 'sains-teknologi']);

        // Nama berbeda tetapi slug otomatis bentrok.
        $this->actingAs($user)
            ->post(route('admin-master.categories.store'), ['name' => 'Sains Teknologi'])
            ->assertSessionHasErrors('slug');

        // Slug manual diterima.
        $this->actingAs($user)
            ->post(route('admin-master.categories.store'), ['name' => 'Sains Teknologi', 'slug' => 'sains-lain'])
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('categories', ['slug' => 'sains-lain']);
    }

    public function test_category_can_keep_its_own_name_and_slug_on_update(): void
    {
        $category = Category::factory()->create(['name' => 'Tetap', 'slug' => 'tetap']);

        $this->actingAs(User::factory()->adminMaster()->create())
            ->put(route('admin-master.categories.update', $category), ['name' => 'Tetap', 'slug' => 'tetap', 'description' => 'Baru'])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'description' => 'Baru']);
    }

    public function test_duplicate_category_name_and_shelf_code_are_rejected(): void
    {
        $user = User::factory()->adminMaster()->create();
        Category::factory()->create(['name' => 'Sama', 'slug' => 'sama']);
        Shelf::factory()->create(['code' => 'RK-A1']);

        $this->actingAs($user)->post(route('admin-master.categories.store'), ['name' => 'Sama'])->assertSessionHasErrors('name');
        $this->actingAs($user)
            ->post(route('admin-master.shelves.store'), ['code' => 'rk-a1', 'name' => 'Rak Kedua'])
            ->assertSessionHasErrors('code');
    }

    public function test_shelf_code_is_stored_in_uppercase(): void
    {
        $this->actingAs(User::factory()->adminMaster()->create())
            ->post(route('admin-master.shelves.store'), ['code' => 'rk-z9', 'name' => 'Rak Z']);

        $this->assertDatabaseHas('shelves', ['code' => 'RK-Z9']);
    }

    public function test_publisher_email_and_phone_are_validated(): void
    {
        $this->actingAs(User::factory()->adminMaster()->create())
            ->post(route('admin-master.publishers.store'), ['name' => 'P', 'email' => 'bukan-email', 'phone' => 'abc'])
            ->assertSessionHasErrors(['email', 'phone']);
    }

    // ------------------------------------------------------------------ aturan hapus

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
    #[DataProvider('usedByBooks')]
    public function test_data_used_by_books_cannot_be_deleted_even_by_soft_deleted_books(string $resource, string $modelClass, string $foreignKey): void
    {
        $user = User::factory()->adminMaster()->create();
        $model = $modelClass::factory()->create();
        $book = Book::factory()->create([$foreignKey => $model->id]);
        $table = $model->getTable();
        $url = route("admin-master.{$resource}.destroy", $model->id);

        // Dipakai buku aktif: ditolak.
        $this->actingAs($user)->delete($url)->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas($table, ['id' => $model->id]);

        // Buku di-soft-delete: baris buku masih ada, tetap ditolak.
        $book->delete();
        $this->actingAs($user)->delete($url)->assertSessionHas('error');
        $this->assertDatabaseHas($table, ['id' => $model->id]);

        // Buku benar-benar dihapus: baru boleh dihapus.
        $book->forceDelete();
        $this->actingAs($user)->delete($url)->assertSessionHas('success');
        $this->assertDatabaseMissing($table, ['id' => $model->id]);
    }

    // ------------------------------------------------------------------ pencarian & pagination

    public function test_index_is_paginated_and_searchable(): void
    {
        Category::factory()->count(12)->create();
        Category::factory()->create(['name' => 'Zzz Unik', 'slug' => 'zzz-unik']);
        $user = User::factory()->adminMaster()->create();

        $this->actingAs($user)
            ->get('/admin-master/categories')
            ->assertOk()
            ->assertSee('page-link')
            ->assertViewHas('items', fn ($items) => $items->count() === 10 && $items->total() === 13);

        $this->actingAs($user)
            ->get('/admin-master/categories?q=Zzz')
            ->assertSee('Zzz Unik')
            ->assertViewHas('items', fn ($items) => $items->total() === 1);

        $this->actingAs($user)
            ->get('/admin-master/categories?q=tidak-ada-sama-sekali')
            ->assertSee('Tidak ada data yang cocok');
    }

    public function test_index_shows_book_count(): void
    {
        $category = Category::factory()->create(['name' => 'Berisi Buku', 'slug' => 'berisi-buku']);
        Book::factory()->count(3)->create(['category_id' => $category->id]);

        $this->actingAs(User::factory()->adminMaster()->create())
            ->get('/admin-master/categories')
            ->assertViewHas('items', fn ($items) => $items->first()->books_count === 3);
    }

    // ------------------------------------------------------------------ upload foto penulis

    public function test_author_photo_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->adminMaster()->create();

        $this->actingAs($user)->post(route('admin-master.authors.store'), [
            'name' => 'Penulis Berfoto',
            'photo' => UploadedFile::fake()->image('foto.jpg', 200, 200),
        ])->assertSessionDoesntHaveErrors();

        $author = Author::where('name', 'Penulis Berfoto')->firstOrFail();
        $first = $author->photo;
        $this->assertStringStartsWith('authors/', $first);
        Storage::disk('public')->assertExists($first);

        // Ganti foto: file lama terhapus.
        $this->actingAs($user)->put(route('admin-master.authors.update', $author), [
            'name' => 'Penulis Berfoto',
            'photo' => UploadedFile::fake()->image('baru.png', 200, 200),
        ]);
        $second = $author->fresh()->photo;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        // Hapus foto lewat centang.
        $this->actingAs($user)->put(route('admin-master.authors.update', $author), [
            'name' => 'Penulis Berfoto',
            'remove_photo' => '1',
        ]);
        $this->assertNull($author->fresh()->photo);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_deleting_author_removes_photo_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->adminMaster()->create();

        $this->actingAs($user)->post(route('admin-master.authors.store'), [
            'name' => 'Hapus Foto',
            'photo' => UploadedFile::fake()->image('foto.jpg'),
        ]);
        $author = Author::where('name', 'Hapus Foto')->firstOrFail();
        $path = $author->photo;

        $this->actingAs($user)->delete(route('admin-master.authors.destroy', $author));

        Storage::disk('public')->assertMissing($path);
    }

    public function test_invalid_or_oversized_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->adminMaster()->create();

        $this->actingAs($user)->post(route('admin-master.authors.store'), [
            'name' => 'Salah Format',
            'photo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('photo');

        $this->actingAs($user)->post(route('admin-master.authors.store'), [
            'name' => 'Terlalu Besar',
            'photo' => UploadedFile::fake()->image('besar.jpg')->size(3000),
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('authors', ['name' => 'Salah Format']);
        $this->assertDatabaseMissing('authors', ['name' => 'Terlalu Besar']);
    }

    // ------------------------------------------------------------------ menu

    public function test_sidebar_menu_for_master_data_is_enabled(): void
    {
        $this->actingAs(User::factory()->adminMaster()->create())
            ->get('/admin-master/dashboard')
            ->assertSee(route('admin-master.categories.index'), false)
            ->assertSee(route('admin-master.shelves.index'), false);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/super-admin/dashboard')
            ->assertSee(route('super-admin.authors.index'), false);
    }

    public function test_active_menu_item_is_highlighted_on_master_pages(): void
    {
        $this->actingAs(User::factory()->adminMaster()->create())
            ->get('/admin-master/categories')
            ->assertSee('aria-current="page"', false);
    }
}
