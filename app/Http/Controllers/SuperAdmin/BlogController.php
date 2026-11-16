<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BlogRequest;
use App\Models\ActivityLog;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Services\ImageUploader;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class BlogController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(private readonly ImageUploader $images)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('manage-library');

        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));
        $categoryId = max(0, $request->integer('category'));
        $status = $request->string('status')->toString();
        $status = in_array($status, ['draft', 'published', 'scheduled'], true) ? $status : '';

        $posts = Blog::query()
            ->with(['category:id,name', 'author:id,name'])
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                $w->where('title', 'like', "%{$term}%")->orWhere('excerpt', 'like', "%{$term}%");
            }))
            ->when($categoryId > 0, fn ($q) => $q->where('blog_category_id', $categoryId))
            ->when($status === 'draft', fn ($q) => $q->where('status', Blog::STATUS_DRAFT))
            ->when($status === 'published', fn ($q) => $q->where('status', Blog::STATUS_PUBLISHED)->where('published_at', '<=', now()))
            ->when($status === 'scheduled', fn ($q) => $q->where('status', Blog::STATUS_PUBLISHED)->where('published_at', '>', now()))
            ->latest()->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('super-admin.blogs.index', [
            'posts' => $posts,
            'categories' => BlogCategory::query()->orderBy('name')->pluck('name', 'id'),
            'filters' => ['q' => $term, 'category' => $categoryId, 'status' => $status],
            'hasFilters' => $term !== '' || $categoryId > 0 || $status !== '',
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-library');

        return view('super-admin.blogs.form', $this->formData(new Blog(['status' => Blog::STATUS_DRAFT])));
    }

    public function store(BlogRequest $request): RedirectResponse
    {
        Gate::authorize('manage-library');

        $data = $request->validated();
        $thumbnail = $request->hasFile('thumbnail') ? $this->images->store($request->file('thumbnail'), 'blogs') : null;

        try {
            $blog = DB::transaction(function () use ($data, $thumbnail, $request) {
                return Blog::create([
                    'blog_category_id' => $this->resolveCategory($data['category_name'] ?? null),
                    'author_id' => $request->user()->id,
                    'title' => $data['title'],
                    'slug' => $data['slug'] ?? $this->uniqueSlug($data['title']),
                    'thumbnail' => $thumbnail,
                    'excerpt' => $data['excerpt'] ?? null,
                    'content' => $data['content'],
                    'status' => $data['status'],
                    'published_at' => $this->publicationDate($data['status'], $data['published_at'] ?? null),
                ]);
            });
        } catch (Throwable $e) {
            $this->images->delete($thumbnail);

            throw $e;
        }

        ActivityLog::record('blog.created', 'Menambah artikel: ' . $blog->title, $blog, ['status' => $blog->status]);

        return redirect()->route('super-admin.blogs.show', $blog)->with('success', 'Artikel berhasil disimpan.');
    }

    /** Pratinjau artikel (draft dan terjadwal juga bisa dilihat di sini). */
    public function show(Blog $blog): View
    {
        Gate::authorize('manage-library');

        $blog->load(['category', 'author']);

        return view('super-admin.blogs.show', [
            'blog' => $blog,
            'paragraphs' => preg_split('/\R{2,}/', trim($blog->content)) ?: [],
        ]);
    }

    public function edit(Blog $blog): View
    {
        Gate::authorize('manage-library');

        return view('super-admin.blogs.form', $this->formData($blog->load('category')));
    }

    public function update(BlogRequest $request, Blog $blog): RedirectResponse
    {
        Gate::authorize('manage-library');

        $data = $request->validated();
        $newThumbnail = $request->hasFile('thumbnail') ? $this->images->store($request->file('thumbnail'), 'blogs') : null;
        $thumbnailChanged = $newThumbnail !== null || $request->boolean('remove_thumbnail');
        $oldThumbnail = $blog->thumbnail;

        try {
            DB::transaction(function () use ($blog, $data, $newThumbnail, $request) {
                $attributes = [
                    'blog_category_id' => $this->resolveCategory($data['category_name'] ?? null),
                    'title' => $data['title'],
                    'slug' => $data['slug'] ?? $blog->slug,
                    'excerpt' => $data['excerpt'] ?? null,
                    'content' => $data['content'],
                    'status' => $data['status'],
                    'published_at' => $this->publicationDate($data['status'], $data['published_at'] ?? null, $blog->published_at),
                ];

                if ($newThumbnail !== null) {
                    $attributes['thumbnail'] = $newThumbnail;
                } elseif ($request->boolean('remove_thumbnail')) {
                    $attributes['thumbnail'] = null;
                }

                $blog->update($attributes);
            });
        } catch (Throwable $e) {
            $this->images->delete($newThumbnail);

            throw $e;
        }

        if ($thumbnailChanged) {
            $this->images->delete($oldThumbnail);
        }

        ActivityLog::record('blog.updated', 'Mengubah artikel: ' . $blog->title, $blog, ['status' => $blog->status]);

        return redirect()->route('super-admin.blogs.show', $blog)->with('success', 'Artikel berhasil diperbarui.');
    }

    public function publish(Blog $blog): RedirectResponse
    {
        Gate::authorize('manage-library');

        // Terbit sekarang. Tanggal lampau dipertahankan, tanggal kosong atau masa depan diganti sekarang.
        $at = $blog->published_at && $blog->published_at->isPast() ? $blog->published_at : now();

        $blog->update(['status' => Blog::STATUS_PUBLISHED, 'published_at' => $at]);

        ActivityLog::record('blog.published', 'Mempublikasikan artikel: ' . $blog->title, $blog);

        return back()->with('success', 'Artikel berhasil dipublikasikan.');
    }

    public function unpublish(Blog $blog): RedirectResponse
    {
        Gate::authorize('manage-library');

        $blog->update(['status' => Blog::STATUS_DRAFT, 'published_at' => null]);

        ActivityLog::record('blog.unpublished', 'Menarik artikel dari publik: ' . $blog->title, $blog);

        return back()->with('success', 'Artikel ditarik dari publik dan kembali menjadi draft.');
    }

    public function destroy(Blog $blog): RedirectResponse
    {
        Gate::authorize('manage-library');

        $title = $blog->title;
        $id = $blog->id;

        // Soft delete: artikel bisa dipulihkan lewat database bila diperlukan.
        $blog->delete();

        ActivityLog::record('blog.deleted', 'Menghapus artikel: ' . $title, null, ['id' => $id, 'title' => $title]);

        return redirect()->route('super-admin.blogs.index')->with('success', 'Artikel berhasil dihapus.');
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function formData(Blog $blog): array
    {
        return [
            'blog' => $blog,
            'categoryNames' => BlogCategory::query()->orderBy('name')->pluck('name'),
        ];
    }

    /** Draft tidak punya tanggal. Artikel terbit memakai tanggal input, tanggal lama, atau sekarang. */
    private function publicationDate(string $status, ?string $input, ?Carbon $existing = null): ?Carbon
    {
        if ($status !== Blog::STATUS_PUBLISHED) {
            return null;
        }

        if ($input !== null && $input !== '') {
            return Carbon::parse($input);
        }

        return $existing ?? now();
    }

    private function resolveCategory(?string $name): ?int
    {
        $name = trim((string) $name);
        $slug = Str::slug($name);

        if ($name === '' || $slug === '') {
            return null;
        }

        return BlogCategory::query()->firstOrCreate(['slug' => $slug], ['name' => $name])->id;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'artikel';
        $slug = $base;
        $counter = 2;

        while (Blog::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
