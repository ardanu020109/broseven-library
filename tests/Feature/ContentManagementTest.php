<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\LibraryProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function outsiders(): array
    {
        return ['admin master' => ['admin-master'], 'admin karyawan' => ['admin-karyawan'], 'anggota' => ['anggota']];
    }

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    #[DataProvider('outsiders')]
    public function test_only_super_admin_can_open_content_modules(string $role): void
    {
        $user = User::factory()->role(Role::from($role))->create();

        foreach (['library-profile', 'blogs', 'blogs/create', 'activity-logs'] as $path) {
            $this->actingAs($user)->get("/super-admin/{$path}")->assertForbidden();
        }

        $this->actingAs($user)->put('/super-admin/library-profile', ['name' => 'x'])->assertForbidden();
        $this->actingAs($user)->post('/super-admin/blogs', ['title' => 'x'])->assertForbidden();
    }

    // ================================================================ profil perpustakaan

    /** @param array<string, mixed> $override */
    private function profilePayload(array $override = []): array
    {
        return array_merge([
            'name' => 'Perpustakaan Baru',
            'tagline' => 'Tagline Baru',
            'description' => 'Deskripsi baru perpustakaan.',
            'address' => 'Jl. Baru No. 1',
            'phone' => '021 123 456',
            'email' => 'baru@uji.test',
            'website' => 'https://baru.test',
            'head_name' => 'Bu Kepala',
            'operating_hours' => 'Senin - Jumat: 08.00 - 15.00',
            'borrowing_rules' => 'Aturan baru.',
            'max_books_per_loan' => 5,
            'loan_duration_days' => 14,
            'fine_per_day' => 2500,
            'social' => ['instagram' => 'https://instagram.com/baru', 'facebook' => ''],
        ], $override);
    }

    public function test_profile_page_opens_even_when_no_profile_exists(): void
    {
        $this->actingAs($this->superAdmin())->get('/super-admin/library-profile')->assertOk()->assertSee('Aturan Peminjaman');
    }

    public function test_profile_is_saved_and_used_across_public_pages(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put(route('super-admin.library-profile.update'), $this->profilePayload())
            ->assertRedirect(route('super-admin.library-profile.edit'))
            ->assertSessionDoesntHaveErrors();

        $profile = LibraryProfile::firstOrFail();
        $this->assertSame(5, $profile->max_books_per_loan);
        $this->assertSame(14, $profile->loan_duration_days);
        $this->assertSame(['instagram' => 'https://instagram.com/baru'], $profile->social_media); // tautan kosong dibuang
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'library.updated']);

        auth()->logout();
        $this->get('/')->assertSee('Perpustakaan Baru')->assertSee('Tagline Baru')->assertSee('Jl. Baru No. 1');
        $this->get('/tentang-kami')->assertSee('Rp2.500')->assertSee('Bu Kepala');
        $this->get('/kontak')->assertSee('baru@uji.test');
    }

    public function test_updating_profile_twice_keeps_a_single_row(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put(route('super-admin.library-profile.update'), $this->profilePayload());
        $this->actingAs($admin)->put(route('super-admin.library-profile.update'), $this->profilePayload(['name' => 'Nama Kedua']));

        $this->assertDatabaseCount('library_profiles', 1);
        $this->assertDatabaseHas('library_profiles', ['name' => 'Nama Kedua']);
    }

    public function test_profile_validation(): void
    {
        $this->actingAs($this->superAdmin())->put(route('super-admin.library-profile.update'), $this->profilePayload([
            'name' => '',
            'max_books_per_loan' => 0,
            'loan_duration_days' => 100,
            'fine_per_day' => -1,
            'website' => 'javascript:alert(1)',
            'email' => 'bukan-email',
            'social' => ['instagram' => 'ftp://salah'],
        ]))->assertSessionHasErrors(['name', 'max_books_per_loan', 'loan_duration_days', 'fine_per_day', 'website', 'email', 'social.instagram']);

        $this->assertDatabaseCount('library_profiles', 0);
    }

    public function test_logo_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put(route('super-admin.library-profile.update'), $this->profilePayload([
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]));
        $first = LibraryProfile::firstOrFail()->logo;
        $this->assertStringStartsWith('logos/', $first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($admin)->put(route('super-admin.library-profile.update'), $this->profilePayload([
            'logo' => UploadedFile::fake()->image('logo2.jpg', 200, 200),
        ]));
        $second = LibraryProfile::firstOrFail()->logo;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($admin)->put(route('super-admin.library-profile.update'), $this->profilePayload(['remove_logo' => '1']));
        $this->assertNull(LibraryProfile::firstOrFail()->logo);
        Storage::disk('public')->assertMissing($second);
    }

    // ================================================================ blog

    /** @param array<string, mixed> $override */
    private function blogPayload(array $override = []): array
    {
        return array_merge([
            'title' => 'Artikel Uji Pertama',
            'excerpt' => 'Ringkasan uji.',
            'content' => "Paragraf satu.\n\nParagraf dua.",
            'category_name' => 'Berita Uji',
            'status' => 'draft',
        ], $override);
    }

    public function test_draft_article_is_saved_but_hidden_from_public(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('super-admin.blogs.store'), $this->blogPayload())->assertSessionDoesntHaveErrors();

        $blog = Blog::where('title', 'Artikel Uji Pertama')->firstOrFail();
        $this->assertSame('artikel-uji-pertama', $blog->slug);
        $this->assertSame($admin->id, $blog->author_id);
        $this->assertNull($blog->published_at);
        $this->assertSame('Berita Uji', $blog->category->name);
        $this->assertDatabaseHas('activity_logs', ['action' => 'blog.created']);

        auth()->logout();
        $this->get('/blog')->assertDontSee('Artikel Uji Pertama');
        $this->get('/blog/artikel-uji-pertama')->assertNotFound();
    }

    public function test_published_article_without_date_appears_publicly_right_away(): void
    {
        $this->actingAs($this->superAdmin())->post(route('super-admin.blogs.store'), $this->blogPayload(['status' => 'published']));

        $blog = Blog::firstOrFail();
        $this->assertNotNull($blog->published_at);

        auth()->logout();
        $this->get('/blog')->assertSee('Artikel Uji Pertama');
        $this->get('/blog/artikel-uji-pertama')->assertOk();
    }

    public function test_future_date_makes_article_scheduled_and_hidden_until_then(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('super-admin.blogs.store'), $this->blogPayload([
            'status' => 'published',
            'published_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ]));

        $this->actingAs($admin)->get('/super-admin/blogs')->assertSee('Terjadwal');
        $this->actingAs($admin)->get('/super-admin/blogs?status=scheduled')->assertSee('Artikel Uji Pertama');
        $this->actingAs($admin)->get('/super-admin/blogs?status=published')->assertDontSee('Artikel Uji Pertama');

        auth()->logout();
        $this->get('/blog')->assertDontSee('Artikel Uji Pertama');
    }

    public function test_duplicate_titles_get_unique_slugs_and_manual_slug_must_be_unique(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('super-admin.blogs.store'), $this->blogPayload(['title' => 'Judul Sama']));
        $this->actingAs($admin)->post(route('super-admin.blogs.store'), $this->blogPayload(['title' => 'Judul Sama']));

        $this->assertSame(['judul-sama', 'judul-sama-2'], Blog::orderBy('id')->pluck('slug')->all());

        $this->actingAs($admin)->post(route('super-admin.blogs.store'), $this->blogPayload(['title' => 'Lain', 'slug' => 'Judul Sama']))
            ->assertSessionHasErrors('slug');
    }

    public function test_existing_category_is_reused_by_name(): void
    {
        $admin = $this->superAdmin();
        BlogCategory::factory()->create(['name' => 'Tips Uji', 'slug' => 'tips-uji']);

        $this->actingAs($admin)->post(route('super-admin.blogs.store'), $this->blogPayload(['category_name' => 'Tips Uji']));
        $this->actingAs($admin)->post(route('super-admin.blogs.store'), $this->blogPayload(['title' => 'Kedua', 'category_name' => 'tips uji']));

        $this->assertSame(1, BlogCategory::where('slug', 'tips-uji')->count());
        $this->assertSame(2, Blog::where('blog_category_id', BlogCategory::where('slug', 'tips-uji')->value('id'))->count());
    }

    public function test_blog_validation(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superAdmin())->post(route('super-admin.blogs.store'), [])
            ->assertSessionHasErrors(['title', 'content', 'status']);

        $this->actingAs($this->superAdmin())->post(route('super-admin.blogs.store'), $this->blogPayload([
            'status' => 'rahasia',
            'published_at' => 'bukan-tanggal',
            'thumbnail' => UploadedFile::fake()->create('dokumen.pdf', 50, 'application/pdf'),
        ]))->assertSessionHasErrors(['status', 'published_at', 'thumbnail']);
    }

    public function test_publish_and_unpublish_actions(): void
    {
        $admin = $this->superAdmin();
        $blog = Blog::factory()->draft()->create(['title' => 'Siap Terbit', 'slug' => 'siap-terbit']);

        $this->actingAs($admin)->post(route('super-admin.blogs.publish', $blog))->assertSessionHas('success');
        $blog->refresh();
        $this->assertSame('published', $blog->status);
        $this->assertNotNull($blog->published_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'blog.published']);

        auth()->logout();
        $this->get('/blog/siap-terbit')->assertOk();

        $this->actingAs($admin)->post(route('super-admin.blogs.unpublish', $blog))->assertSessionHas('success');
        $blog->refresh();
        $this->assertSame('draft', $blog->status);
        $this->assertNull($blog->published_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'blog.unpublished']);

        auth()->logout();
        $this->get('/blog/siap-terbit')->assertNotFound();
    }

    public function test_article_can_be_updated_with_thumbnail_and_keeps_its_slug(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();
        $blog = Blog::factory()->create(['title' => 'Lama', 'slug' => 'slug-tetap']);

        $this->actingAs($admin)->put(route('super-admin.blogs.update', $blog), $this->blogPayload([
            'title' => 'Judul Baru', 'status' => 'published',
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg', 400, 225),
        ]))->assertRedirect(route('super-admin.blogs.show', $blog));

        $blog->refresh();
        $this->assertSame('Judul Baru', $blog->title);
        $this->assertSame('slug-tetap', $blog->slug);
        $this->assertStringStartsWith('blogs/', $blog->thumbnail);
        Storage::disk('public')->assertExists($blog->thumbnail);

        $first = $blog->thumbnail;
        $this->actingAs($admin)->put(route('super-admin.blogs.update', $blog), $this->blogPayload([
            'status' => 'published', 'remove_thumbnail' => '1',
        ]));
        $this->assertNull($blog->fresh()->thumbnail);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_preview_shows_draft_to_super_admin_only_and_escapes_html(): void
    {
        $admin = $this->superAdmin();
        $blog = Blog::factory()->draft()->create([
            'slug' => 'draf-xss',
            'content' => "Aman.\n\n<script>alert('x')</script>",
        ]);

        $this->actingAs($admin)->get(route('super-admin.blogs.show', $blog))
            ->assertOk()
            ->assertSee('Draft.')
            ->assertSee('Aman.')
            ->assertDontSee("<script>alert('x')</script>", false);

        auth()->logout();
        $this->get('/blog/draf-xss')->assertNotFound();
    }

    public function test_article_is_soft_deleted(): void
    {
        $blog = Blog::factory()->create(['slug' => 'akan-dihapus']);

        $this->actingAs($this->superAdmin())->delete(route('super-admin.blogs.destroy', $blog))
            ->assertRedirect(route('super-admin.blogs.index'));

        $this->assertSoftDeleted($blog);
        $this->get('/blog/akan-dihapus')->assertNotFound();
        $this->assertDatabaseHas('activity_logs', ['action' => 'blog.deleted']);
    }

    public function test_blog_list_search_and_status_filter(): void
    {
        $admin = $this->superAdmin();
        Blog::factory()->create(['title' => 'Terbit Alpha', 'slug' => 'terbit-alpha']);
        Blog::factory()->draft()->create(['title' => 'Draf Beta', 'slug' => 'draf-beta']);

        $this->actingAs($admin)->get('/super-admin/blogs?q=Alpha')->assertSee('Terbit Alpha')->assertDontSee('Draf Beta');
        $this->actingAs($admin)->get('/super-admin/blogs?status=draft')->assertSee('Draf Beta')->assertDontSee('Terbit Alpha');
        $this->actingAs($admin)->get('/super-admin/blogs?status=published')->assertSee('Terbit Alpha')->assertDontSee('Draf Beta');
        $this->actingAs($admin)->get('/super-admin/blogs?status=ngawur&category=abc')->assertOk();
    }

    // ================================================================ log aktivitas

    public function test_activity_log_lists_entries_with_labels(): void
    {
        $admin = $this->superAdmin();
        ActivityLog::record('auth.login', 'Login berhasil uji', null, ['contoh' => 'nilai'], $admin->id);

        $this->actingAs($admin)->get('/super-admin/activity-logs')
            ->assertOk()
            ->assertSee('Login berhasil uji')
            ->assertSee($admin->name)
            ->assertSee('auth.login');
    }

    public function test_activity_log_filters(): void
    {
        $admin = $this->superAdmin();
        $other = User::factory()->adminKaryawan()->create(['name' => 'Petugas Uji']);

        ActivityLog::record('auth.login', 'Aksi login uji', null, [], $admin->id);
        ActivityLog::record('book.created', 'Aksi buku uji', null, [], $other->id);
        ActivityLog::query()->where('action', 'book.created')->update(['created_at' => now()->subDays(10)]);

        $this->actingAs($admin)->get('/super-admin/activity-logs?q=buku')->assertSee('Aksi buku uji')->assertDontSee('Aksi login uji');
        $this->actingAs($admin)->get('/super-admin/activity-logs?action=auth.login')->assertSee('Aksi login uji')->assertDontSee('Aksi buku uji');
        $this->actingAs($admin)->get('/super-admin/activity-logs?user=' . $other->id)->assertSee('Aksi buku uji')->assertDontSee('Aksi login uji');
        $this->actingAs($admin)->get('/super-admin/activity-logs?from=' . now()->subDays(2)->toDateString())
            ->assertSee('Aksi login uji')->assertDontSee('Aksi buku uji');
        $this->actingAs($admin)->get('/super-admin/activity-logs?to=' . now()->subDays(5)->toDateString())
            ->assertSee('Aksi buku uji')->assertDontSee('Aksi login uji');
        $this->actingAs($admin)->get('/super-admin/activity-logs?from=bukan-tanggal&to=2026-99-99&user=abc')->assertOk();
    }

    public function test_activity_log_is_paginated(): void
    {
        $admin = $this->superAdmin();

        for ($i = 1; $i <= 21; $i++) {
            ActivityLog::record('auth.login', "Entri {$i}", null, [], $admin->id);
        }

        $this->actingAs($admin)->get('/super-admin/activity-logs')
            ->assertSee('page-link')
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 20 && $logs->total() === 21);
    }

    public function test_all_super_admin_menu_items_are_now_enabled(): void
    {
        $this->actingAs($this->superAdmin())->get('/super-admin/dashboard')
            ->assertSee(route('super-admin.users.index'), false)
            ->assertSee(route('super-admin.library-profile.edit'), false)
            ->assertSee(route('super-admin.blogs.index'), false)
            ->assertSee(route('super-admin.activity-logs.index'), false);
    }
}
