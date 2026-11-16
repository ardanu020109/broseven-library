@props(['book', 'reason' => null])

<div class="card book-card borrow-card h-100 {{ $reason ? 'is-disabled' : '' }}">
    <div class="book-cover-wrap position-relative">
        <img src="{{ $book->cover_url }}" alt="Sampul {{ $book->title }}" class="book-cover" loading="lazy">

        <input type="checkbox" class="form-check-input borrow-check" name="books[]" value="{{ $book->id }}"
               id="book{{ $book->id }}" aria-label="Pilih {{ $book->title }}"
               @if ($reason) disabled data-locked="1" @endif>
    </div>
    <div class="card-body d-flex flex-column">
        <span class="badge-category mb-2">{{ $book->category->name }}</span>
        <h3 class="book-title h6">
            <a href="{{ route('anggota.catalog.show', $book) }}">{{ $book->title }}</a>
        </h3>
        <p class="text-muted small mb-2">{{ $book->author->name }}</p>

        <div class="mt-auto">
            @if ($reason)
                <span class="badge badge-status badge-overdue">{{ $reason }}</span>
            @else
                <span class="badge badge-status badge-available">Tersedia {{ $book->available_stock }}</span>
            @endif
        </div>
    </div>
</div>
