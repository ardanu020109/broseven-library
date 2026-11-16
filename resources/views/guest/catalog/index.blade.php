@extends('layouts.guest')

@section('title', 'Katalog Buku')
@section('meta_description', 'Telusuri koleksi buku ' . $libraryProfile->name . ' berdasarkan judul, ISBN, kategori, penulis, dan penerbit.')

@section('content')
<section class="page-header">
    <div class="container">
        <h1>Katalog Buku</h1>
        <p>Cari dan temukan buku yang Anda butuhkan.</p>
    </div>
</section>

<section class="section">
    <div class="container">

        <form method="GET" action="{{ route('catalog.index') }}" class="card filter-card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <label for="q" class="form-label small fw-semibold">Judul atau ISBN</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" id="q" name="q" value="{{ $filters['q'] }}" maxlength="100"
                                   class="form-control" placeholder="Contoh: Laskar Pelangi">
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-2">
                        <label for="category" class="form-label small fw-semibold">Kategori</label>
                        <select id="category" name="category" class="form-select" data-autosubmit>
                            <option value="">Semua</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 col-lg-2">
                        <label for="author" class="form-label small fw-semibold">Penulis</label>
                        <select id="author" name="author" class="form-select" data-autosubmit>
                            <option value="">Semua</option>
                            @foreach ($authors as $author)
                                <option value="{{ $author->id }}" @selected($filters['author'] === $author->id)>{{ $author->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 col-lg-2">
                        <label for="publisher" class="form-label small fw-semibold">Penerbit</label>
                        <select id="publisher" name="publisher" class="form-select" data-autosubmit>
                            <option value="">Semua</option>
                            @foreach ($publishers as $publisher)
                                <option value="{{ $publisher->id }}" @selected($filters['publisher'] === $publisher->id)>{{ $publisher->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 col-lg-2">
                        <label for="availability" class="form-label small fw-semibold">Ketersediaan</label>
                        <select id="availability" name="availability" class="form-select" data-autosubmit>
                            <option value="">Semua</option>
                            <option value="available" @selected($filters['availability'] === 'available')>Tersedia</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-end gap-3 mt-3">
                    <div>
                        <label for="sort" class="form-label small fw-semibold mb-1">Urutkan</label>
                        <select id="sort" name="sort" class="form-select form-select-sm" data-autosubmit>
                            <option value="newest" @selected($filters['sort'] === 'newest')>Terbaru</option>
                            <option value="title" @selected($filters['sort'] === 'title')>Judul (A-Z)</option>
                            <option value="year" @selected($filters['sort'] === 'year')>Tahun terbit</option>
                            <option value="popular" @selected($filters['sort'] === 'popular')>Terpopuler</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i>Terapkan
                    </button>

                    @if ($hasFilters)
                        <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-circle me-1"></i>Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>

        @if ($books->total() > 0)
            <p class="text-muted small mb-3">
                Menampilkan {{ $books->firstItem() }}–{{ $books->lastItem() }} dari {{ $books->total() }} buku
            </p>
        @endif

        <div class="row g-4">
            @forelse ($books as $book)
                <div class="col-6 col-md-4 col-lg-3">
                    <x-book-card :book="$book" />
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-search"></i>
                        <p class="mb-2">Tidak ada buku yang cocok dengan pencarian Anda.</p>
                        @if ($hasFilters)
                            <a href="{{ route('catalog.index') }}" class="btn btn-outline-primary btn-sm">Hapus semua filter</a>
                        @endif
                    </div>
                </div>
            @endforelse
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $books->links() }}
        </div>
    </div>
</section>
@endsection
