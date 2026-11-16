<?php

namespace App\Http\Controllers\Anggota;

use App\Enums\BorrowingStatus;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BorrowingItem;
use App\Models\Category;
use App\Models\LibraryProfile;
use App\Services\BorrowingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(private readonly BorrowingService $borrowings)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));
        $slug = $request->string('category')->toString();
        $category = $slug !== '' ? Category::query()->where('slug', $slug)->first() : null;
        $onlyAvailable = $request->string('availability')->toString() === 'available';

        $books = Book::query()->active()
            ->with(['author:id,name', 'category:id,name'])
            ->search($term)
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($onlyAvailable, fn ($q) => $q->available())
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        return view('anggota.catalog.index', [
            'books' => $books,
            'engaged' => $this->engagedBookIds($user->id, $books->getCollection()->pluck('id')->all()),
            'problem' => $this->borrowings->eligibilityProblem($user),
            'max' => max(1, (int) LibraryProfile::current()->max_books_per_loan),
            'categories' => Category::query()->orderBy('name')->get(['name', 'slug']),
            'filters' => ['q' => $term, 'category' => $category?->slug ?? '', 'availability' => $onlyAvailable ? 'available' : ''],
            'hasFilters' => $term !== '' || $category !== null || $onlyAvailable,
        ]);
    }

    public function show(Request $request, Book $book): View
    {
        abort_unless($book->status === Book::STATUS_ACTIVE, 404);

        $book->load(['author', 'category', 'publisher', 'shelf']);

        return view('anggota.catalog.show', [
            'book' => $book,
            'engaged' => $this->engagedBookIds($request->user()->id, [$book->id])->contains($book->id),
            'problem' => $this->borrowings->eligibilityProblem($request->user()),
        ]);
    }

    /** ID buku (dari daftar) yang sedang dipinjam atau diajukan oleh anggota ini. */
    private function engagedBookIds(int $memberId, array $bookIds)
    {
        return BorrowingItem::query()
            ->whereIn('book_id', $bookIds)
            ->whereHas('borrowing', fn ($b) => $b
                ->where('member_id', $memberId)
                ->whereIn('status', BorrowingStatus::openValues()))
            ->pluck('book_id');
    }
}
