<?php

namespace App\Http\Controllers\AdminMaster;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\ActivityLog;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Setting;
use App\Models\Shelf;
use App\Services\BookStockService;
use App\Services\ImageUploader;
use App\Support\Panel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class BookController extends Controller
{
    private const PER_PAGE = 10;

    /** Kolom buku yang boleh diisi dari formulir (stok TIDAK termasuk). */
    private const FIELDS = [
        'isbn', 'title', 'category_id', 'author_id', 'publisher_id', 'shelf_id', 'shelf_number',
        'publication_year', 'language', 'edition', 'description', 'pages', 'price', 'status',
    ];

    public function __construct(
        private readonly BookStockService $stock,
        private readonly ImageUploader $images,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('manage-master-data');

        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));
        $categoryId = max(0, $request->integer('category'));
        $authorId = max(0, $request->integer('author'));
        $publisherId = max(0, $request->integer('publisher'));

        $availability = $request->string('availability')->toString();
        $availability = in_array($availability, ['available', 'empty', 'low'], true) ? $availability : '';

        $status = $request->string('status')->toString();
        $status = in_array($status, [Book::STATUS_ACTIVE, Book::STATUS_INACTIVE], true) ? $status : '';

        $threshold = (int) Setting::get('low_stock_threshold', 2);

        $books = Book::query()
            ->with(['category:id,name', 'author:id,name', 'publisher:id,name'])
            ->search($term)
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->when($authorId > 0, fn ($q) => $q->where('author_id', $authorId))
            ->when($publisherId > 0, fn ($q) => $q->where('publisher_id', $publisherId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($availability === 'available', fn ($q) => $q->where('available_stock', '>', 0))
            ->when($availability === 'empty', fn ($q) => $q->where('available_stock', 0))
            ->when($availability === 'low', fn ($q) => $q->where('available_stock', '>', 0)->where('available_stock', '<=', $threshold))
            ->latest()->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin-master.books.index', [
            'books' => $books,
            'threshold' => $threshold,
            'filters' => [
                'q' => $term,
                'category' => $categoryId,
                'author' => $authorId,
                'publisher' => $publisherId,
                'availability' => $availability,
                'status' => $status,
            ],
            'hasFilters' => $term !== '' || $categoryId > 0 || $authorId > 0 || $publisherId > 0 || $availability !== '' || $status !== '',
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id'),
            'authors' => Author::query()->orderBy('name')->pluck('name', 'id'),
            'publishers' => Publisher::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-master-data');

        return view('admin-master.books.form', $this->formData(new Book([
            'language' => 'Indonesia',
            'status' => Book::STATUS_ACTIVE,
        ])));
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        Gate::authorize('manage-master-data');

        $data = $request->validated();
        $total = (int) $data['total_stock'];
        $cover = $request->hasFile('cover') ? $this->images->store($request->file('cover'), 'covers') : null;

        try {
            $book = DB::transaction(function () use ($data, $total, $cover, $request) {
                $book = Book::create(Arr::only($data, self::FIELDS) + ['cover' => $cover]);
                $this->stock->initialize($book, $total, $request->user());

                return $book;
            });
        } catch (Throwable $e) {
            $this->images->delete($cover);

            throw $e;
        }

        ActivityLog::record('book.created', 'Menambah buku: ' . $book->title, $book, ['total_stock' => $total]);

        return redirect(Panel::route('books.show', $book))->with('success', 'Buku berhasil ditambahkan.');
    }

    public function show(Book $book): View
    {
        Gate::authorize('manage-master-data');

        $book->load(['category', 'author', 'publisher', 'shelf']);

        return view('admin-master.books.show', [
            'book' => $book,
            'threshold' => (int) Setting::get('low_stock_threshold', 2),
            'histories' => $book->stockHistories()->with('user:id,name')->latest()->latest('id')->take(15)->get(),
            'openTransactions' => $book->borrowingItems()
                ->whereHas('borrowing', fn ($q) => $q->whereIn('status', \App\Enums\BorrowingStatus::openValues()))
                ->count(),
        ]);
    }

    public function edit(Book $book): View
    {
        Gate::authorize('manage-master-data');

        return view('admin-master.books.form', $this->formData($book));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        Gate::authorize('manage-master-data');

        $data = $request->validated();
        $newTotal = (int) $data['total_stock'];
        $newCover = $request->hasFile('cover') ? $this->images->store($request->file('cover'), 'covers') : null;
        $coverChanged = $newCover !== null || $request->boolean('remove_cover');
        $oldCover = $book->cover;
        $stockChanged = false;

        try {
            // Perubahan data buku dan stok satu transaksi: jika stok ditolak, semuanya dibatalkan.
            DB::transaction(function () use (&$stockChanged, $book, $data, $newTotal, $newCover, $request) {
                $attributes = Arr::only($data, self::FIELDS);

                if ($newCover !== null) {
                    $attributes['cover'] = $newCover;
                } elseif ($request->boolean('remove_cover')) {
                    $attributes['cover'] = null;
                }

                $book->update($attributes);

                $stockChanged = $this->stock->adjustTotal($book, $newTotal, $request->user(), $data['stock_note'] ?? null);
            });
        } catch (Throwable $e) {
            $this->images->delete($newCover);

            throw $e;
        }

        if ($coverChanged) {
            $this->images->delete($oldCover);
        }

        ActivityLog::record('book.updated', 'Mengubah buku: ' . $book->title, $book, [
            'stock_changed' => $stockChanged,
            'total_stock' => $newTotal,
        ]);

        return redirect(Panel::route('books.show', $book))->with('success', 'Buku berhasil diperbarui.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        Gate::authorize('manage-master-data');

        $index = Panel::route('books.index');
        $title = $book->title;
        $id = $book->id;

        $blocked = DB::transaction(function () use ($book): ?string {
            // Kunci baris agar tidak bentrok dengan persetujuan peminjaman yang sedang berjalan.
            $locked = Book::query()->lockForUpdate()->findOrFail($book->id);

            if ($locked->borrowed_stock > 0 || $locked->hasOpenTransactions()) {
                return "Buku \"{$locked->title}\" tidak dapat dihapus karena masih memiliki transaksi aktif "
                    . '(menunggu persetujuan atau sedang dipinjam).';
            }

            // Soft delete: riwayat peminjaman dan stok tetap tersimpan.
            $locked->delete();

            return null;
        });

        if ($blocked !== null) {
            return redirect($index)->with('error', $blocked);
        }

        ActivityLog::record('book.deleted', 'Menghapus buku: ' . $title, null, ['id' => $id, 'title' => $title]);

        return redirect($index)->with('success', 'Buku berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function formData(Book $book): array
    {
        return [
            'book' => $book,
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id'),
            'authors' => Author::query()->orderBy('name')->pluck('name', 'id'),
            'publishers' => Publisher::query()->orderBy('name')->pluck('name', 'id'),
            'shelves' => Shelf::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Shelf $shelf) => [$shelf->id => "{$shelf->code} · {$shelf->name}"]),
        ];
    }
}
