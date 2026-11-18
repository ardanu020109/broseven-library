<?php

namespace Tests\Feature\Guest;

use App\Models\Author;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Book;
use App\Models\Category;
use App\Models\LibraryProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LibraryProfile::create([
            'name' => 'Perpustakaan Uji',
            'tagline' => 'Tagline Uji',
            'description' => 'Deskripsi perpustakaan uji.',
            'address' => 'Jl. Uji No. 99',
            'phone' => '021 555 0199',
            'email' => 'info@uji.test',
            'operating_hours' => 'Senin - Jumat: 08.00 - 16.00',
            'max_books_per_loan' => 4,
            'loan_duration_days' => 10,
            'fine_per_day' => 3000,
            'social_media' => [
                'instagram' => 'https://instagram.com/uji',
                'facebook' => 'javascript:alert(1)', // harus disaring
            ],
        ]);
    }

    public function test_home_page_shows_dynamic_profile_stats_and_all_sections(): void
    {
        Book::factory()->count(2)->create();
        User::factory()->count(3)->create();

        $this->get('/')
            ->assertOk()
            ->assertSee('Perpustakaan Uji')
            ->assertSee('Tagline Uji')
            ->assertSee('Jl. Uji No. 99')
            ->assertSee('Buku Terbaru')
            ->assertSee('Buku Populer')
            ->assertSee('Kategori Buku')
            ->assertSee('Tentang Perpustakaan')
            ->assertSee('Artikel Terbaru')
            ->assertSee('Informasi Kontak')
            ->assertSee('Rp3.000')
            ->assertViewHas('stats', fn(array $s) => $s['titles'] === 2 && $s['members'] === 3);
    }

    public function test_home_page_works_without_any_data(): void
    {
        LibraryProfile::query()->delete();

        $this->get('/')->assertOk()->assertSee('Belum ada buku');
    }

    public function test_footer_filters_unsafe_social_links(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('https://instagram.com/uji', false)
            ->assertDontSee('javascript:alert', false);
    }

    public function test_navbar_shows_login_and_register_for_guests_and_dashboard_for_members(): void
    {
        $this->get('/')->assertSee('Daftar Anggota')->assertSee('Masuk');

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('Dashboard')
            ->assertSee('Keluar');
    }

    public function test_about_and_contact_pages_use_profile_data(): void
    {
        $this->get('/tentang-kami')
            ->assertOk()
            ->assertSee('Deskripsi perpustakaan uji.')
            ->assertSee('Maksimal')
            ->assertSee('Rp3.000');

        $this->get('/kontak')
            ->assertOk()
            ->assertSee('info@uji.test')
            ->assertSee('mailto:info@uji.test', false)
            ->assertSee('tel:0215550199', false);
    }

    public function test_catalog_lists_active_books_only(): void
    {
        Book::factory()->create(['title' => 'Buku Aktif Uji']);
        Book::factory()->inactive()->create(['title' => 'Buku Nonaktif Uji']);

        $deleted = Book::factory()->create(['title' => 'Buku Terhapus Uji']);
        $deleted->delete();

        $this->get('/katalog')
            ->assertOk()
            ->assertSee('Buku Aktif Uji')
            ->assertDontSee('Buku Nonaktif Uji')
            ->assertDontSee('Buku Terhapus Uji');
    }

    public function test_catalog_search_by_title_and_isbn(): void
    {
        Book::factory()->create(['title' => 'Alpha Pencarian', 'isbn' => '9786020000001']);
        Book::factory()->create(['title' => 'Beta Lainnya', 'isbn' => '9786020000002']);

        $this->get('/katalog?q=Alpha')->assertSee('Alpha Pencarian')->assertDontSee('Beta Lainnya');
        $this->get('/katalog?q=9786020000002')->assertSee('Beta Lainnya')->assertDontSee('Alpha Pencarian');
    }

    public function test_catalog_filters_by_category_author_and_availability(): void
    {
        $category = Category::factory()->create(['name' => 'Kategori Uji', 'slug' => 'kategori-uji']);
        $author = Author::factory()->create(['name' => 'Penulis Uji']);

        Book::factory()->create(['title' => 'Buku Cocok', 'category_id' => $category->id, 'author_id' => $author->id]);
        Book::factory()->create(['title' => 'Buku Lain']);
        Book::factory()->outOfStock()->create(['title' => 'Buku Habis', 'category_id' => $category->id]);

        $this->get('/katalog?category=kategori-uji')
            ->assertSee('Buku Cocok')->assertSee('Buku Habis')->assertDontSee('Buku Lain');

        $this->get('/katalog?author=' . $author->id)
            ->assertSee('Buku Cocok')->assertDontSee('Buku Habis');

        $this->get('/katalog?category=kategori-uji&availability=available')
            ->assertSee('Buku Cocok')->assertDontSee('Buku Habis');
    }

    public function test_catalog_ignores_garbage_filters_without_error(): void
    {
        Book::factory()->create(['title' => 'Buku Tetap Tampil']);

        $this->get('/katalog?category=tidak-ada&author=abc&publisher=-5&sort=ngawur&availability=x')
            ->assertOk()
            ->assertSee('Buku Tetap Tampil');
    }

    public function test_catalog_is_paginated_with_bootstrap_markup(): void
    {
        Book::factory()->count(13)->create();

        $this->get('/katalog')
            ->assertOk()
            ->assertSee('page-link')
            ->assertViewHas('books', fn($books) => $books->count() === 12 && $books->total() === 13);

        $this->get('/katalog?page=2')
            ->assertViewHas('books', fn($books) => $books->count() === 1);
    }

    public function test_book_detail_shows_stock_and_login_cta_for_guests(): void
    {
        $book = Book::factory()->withStock(10, 7)->create(['title' => 'Detail Uji', 'isbn' => '9786021111111']);

        $this->get("/katalog/{$book->id}")
            ->assertOk()
            ->assertSee('Detail Uji')
            ->assertSee('9786021111111')
            ->assertSee('Masuk untuk Meminjam');
    }

    public function test_book_detail_shows_dashboard_link_for_logged_in_users(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/katalog/{$book->id}")
            ->assertOk()
            ->assertSee('Buka Dashboard')
            ->assertDontSee('Masuk untuk Meminjam');
    }

    public function test_inactive_and_deleted_books_return_404_on_detail(): void
    {
        $inactive = Book::factory()->inactive()->create();
        $deleted = Book::factory()->create();
        $deleted->delete();

        $this->get("/katalog/{$inactive->id}")->assertNotFound();
        $this->get("/katalog/{$deleted->id}")->assertNotFound();
        $this->get('/katalog/99999')->assertNotFound();
    }

    public function test_blog_shows_only_published_articles(): void
    {
        Blog::factory()->create(['title' => 'Artikel Terbit', 'slug' => 'artikel-terbit']);
        Blog::factory()->draft()->create(['title' => 'Artikel Draf', 'slug' => 'artikel-draf']);
        Blog::factory()->create([
            'title' => 'Artikel Terjadwal',
            'slug' => 'artikel-terjadwal',
            'published_at' => now()->addDay(),
        ]);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Artikel Terbit')
            ->assertDontSee('Artikel Draf')
            ->assertDontSee('Artikel Terjadwal');

        $this->get('/blog/artikel-terbit')->assertOk()->assertSee('Artikel Terbit');
        $this->get('/blog/artikel-draf')->assertNotFound();
        $this->get('/blog/artikel-terjadwal')->assertNotFound();
    }

    public function test_blog_filters_by_category_and_search_and_shows_related(): void
    {
        $tips = BlogCategory::factory()->create(['name' => 'Tips Uji', 'slug' => 'tips-uji']);
        $news = BlogCategory::factory()->create(['name' => 'Berita Uji', 'slug' => 'berita-uji']);

        Blog::factory()->create(['title' => 'Satu Tips', 'slug' => 'satu-tips', 'blog_category_id' => $tips->id]);
        Blog::factory()->create(['title' => 'Dua Tips', 'slug' => 'dua-tips', 'blog_category_id' => $tips->id]);
        Blog::factory()->create(['title' => 'Satu Berita', 'slug' => 'satu-berita', 'blog_category_id' => $news->id]);

        // Sidebar "Artikel Terbaru" selalu memuat semua artikel, jadi hasil filter diperiksa lewat data daftar.
        $titles = fn($response) => $response->viewData('posts')->pluck('title')->sort()->values()->all();

        $this->assertSame(['Dua Tips', 'Satu Tips'], $titles($this->get('/blog?kategori=tips-uji')->assertOk()));
        $this->assertSame(['Satu Berita'], $titles($this->get('/blog?q=Berita')->assertOk()));

        $this->get('/blog/satu-tips')
            ->assertOk()
            ->assertSee('Artikel Terkait')
            ->assertSee('Dua Tips');
    }

    public function test_article_content_is_escaped(): void
    {
        Blog::factory()->create([
            'slug' => 'xss',
            'content' => "Paragraf satu.\n\n<script>alert('x')</script>",
        ]);

        $this->get('/blog/xss')
            ->assertOk()
            ->assertSee('Paragraf satu.')
            ->assertDontSee("<script>alert('x')</script>", false);
    }
}
