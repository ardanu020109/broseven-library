<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogController extends Controller
{
    private const PER_PAGE = 9;

    public function index(Request $request): View
    {
        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));
        $slug = $request->string('kategori')->toString();

        $posts = Blog::query()->published()
            ->with(['category', 'author'])
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                $w->where('title', 'like', "%{$term}%")
                    ->orWhere('excerpt', 'like', "%{$term}%");
            }))
            ->when($slug !== '', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->latest('published_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('guest.blog.index', [
            'posts' => $posts,
            'term' => $term,
            'activeCategory' => $slug,
            'categories' => $this->categories(),
            'latestPosts' => $this->latest(),
        ]);
    }

    public function show(string $slug): View
    {
        // Hanya artikel published. Draft atau jadwal masa depan menghasilkan 404.
        $blog = Blog::query()->published()
            ->with(['category', 'author'])
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Blog::query()->published()
            ->with('category')
            ->whereKeyNot($blog->id)
            ->when($blog->blog_category_id, fn ($q) => $q->where('blog_category_id', $blog->blog_category_id))
            ->latest('published_at')
            ->take(3)->get();

        return view('guest.blog.show', [
            'blog' => $blog,
            // Isi artikel berupa teks biasa; baris kosong memisahkan paragraf.
            'paragraphs' => preg_split('/\R{2,}/', trim($blog->content)) ?: [],
            'related' => $related,
            'categories' => $this->categories(),
            'latestPosts' => $this->latest(),
        ]);
    }

    private function categories()
    {
        return BlogCategory::query()
            ->withCount(['blogs' => fn ($q) => $q->published()])
            ->orderBy('name')->get();
    }

    private function latest()
    {
        return Blog::query()->published()
            ->latest('published_at')
            ->take(5)->get(['id', 'title', 'slug', 'published_at']);
    }
}
