@props(['book'])

<div class="card book-card h-100">
    <a href="{{ route('catalog.show', $book) }}" class="book-cover-wrap" tabindex="-1" aria-hidden="true">
        <img src="{{ $book->cover_url }}" alt="Sampul {{ $book->title }}" class="book-cover" loading="lazy">
    </a>
    <div class="card-body d-flex flex-column">
        <span class="badge-category mb-2">{{ $book->category->name }}</span>
        <h3 class="book-title h6">
            <a href="{{ route('catalog.show', $book) }}">{{ $book->title }}</a>
        </h3>
        <p class="text-muted small mb-2">{{ $book->author->name }}</p>

        <div class="mt-auto d-flex flex-wrap gap-1">
            @if ($book->available_stock > 0)
                <span class="badge badge-status badge-available">Tersedia {{ $book->available_stock }}</span>
            @else
                <span class="badge badge-status badge-overdue">Sedang dipinjam semua</span>
            @endif

            @if (($book->borrowed_total ?? 0) > 0)
                <span class="badge badge-status badge-borrowed">Dipinjam {{ $book->borrowed_total }}x</span>
            @endif
        </div>
    </div>
</div>
