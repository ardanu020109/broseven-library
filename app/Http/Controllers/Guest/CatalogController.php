<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CatalogController extends Controller
{
    private const PER_PAGE = 12;
    private const SORTS = ['title', 'year', 'popular'];

    public function index(Request $request): View
    {
        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));
        $slug = $request->string('category')->toString();
        $category = $slug !== '' ? Category::query()->where('slug', $slug)->first() : null;
        $authorId = max(0, $request->integer('author'));
        $publisherId = max(0, $request->integer('publisher'));
        $onlyAvailable = $request->string('availability')->toString() === 'available';
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'newest';

        $query = Book::query()
            ->active()
            ->with(['author', 'category', 'publisher'])
            ->search($term)
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($authorId > 0, fn ($q) => $q->where('author_id', $authorId))
            ->when($publisherId > 0, fn ($q) => $q->where('publisher_id', $publisherId))
            ->when($onlyAvailable, fn ($q) => $q->available());

        match ($sort) {
            'title' => $query->orderBy('title'),
            'year' => $query->orderByDesc('publication_year')->orderBy('title'),
            'popular' => $query->popular()->orderBy('title'),
            default => $query->latest()->latest('id'),
        };

        $books = $query->paginate(self::PER_PAGE)->withQueryString();

        $filters = [
            'q' => $term,
            'category' => $category?->slug ?? '',
            'author' => $authorId,
            'publisher' => $publisherId,
            'availability' => $onlyAvailable ? 'available' : '',
            'sort' => $sort,
        ];

        return view('guest.catalog.index', [
            'books' => $books,
            'filters' => $filters,
            'hasFilters' => $term !== '' || $category !== null || $authorId > 0 || $publisherId > 0 || $onlyAvailable,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'authors' => Author::query()->whereHas('books', fn ($q) => $q->active())->orderBy('name')->get(['id', 'name']),
            'publishers' => Publisher::query()->whereHas('books', fn ($q) => $q->active())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Book $book): View
    {
        // Buku nonaktif tidak boleh terlihat publik. Buku yang dihapus (soft delete) sudah 404 dari route binding.
        abort_unless($book->status === Book::STATUS_ACTIVE, 404);

        $book->load(['author', 'category', 'publisher', 'shelf']);

        $related = Book::query()->active()
            ->with('author')
            ->where('category_id', $book->category_id)
            ->whereKeyNot($book->id)
            ->latest()->latest('id')
            ->take(4)->get();

        return view('guest.catalog.show', [
            'book' => $book,
            'related' => $related,
        ]);
    }
}
