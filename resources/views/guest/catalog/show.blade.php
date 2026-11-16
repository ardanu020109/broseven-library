@extends('layouts.guest')

@section('title', $book->title)
@section('meta_description', Str::limit(strip_tags((string) $book->description), 150))

@section('content')
<section class="page-header page-header-sm">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                <li class="breadcrumb-item"><a href="{{ route('catalog.index') }}">Katalog</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($book->title, 40) }}</li>
            </ol>
        </nav>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row g-5">
            <div class="col-md-4 col-lg-3">
                <img src="{{ $book->cover_url }}" alt="Sampul {{ $book->title }}" class="detail-cover">
            </div>

            <div class="col-md-8 col-lg-9">
                <a href="{{ route('catalog.index', ['category' => $book->category->slug]) }}" class="badge-category">
                    {{ $book->category->name }}
                </a>
                <h1 class="h2 mt-2 mb-1">{{ $book->title }}</h1>
                <p class="text-muted mb-3">
                    oleh <a href="{{ route('catalog.index', ['author' => $book->author_id]) }}">{{ $book->author->name }}</a>
                </p>

                <div class="stock-box mb-4">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        @if ($book->available_stock > 0)
                            <span class="badge badge-status badge-available fs-6">Tersedia</span>
                        @else
                            <span class="badge badge-status badge-overdue fs-6">Sedang dipinjam semua</span>
                        @endif
                    </div>
                    <div class="row text-center g-2">
                        <div class="col-4"><div class="stock-num">{{ $book->total_stock }}</div><div class="stock-label">Total</div></div>
                        <div class="col-4"><div class="stock-num text-success">{{ $book->available_stock }}</div><div class="stock-label">Tersedia</div></div>
                        <div class="col-4"><div class="stock-num text-sienna">{{ $book->borrowed_stock }}</div><div class="stock-label">Dipinjam</div></div>
                    </div>
                </div>

                <div class="mb-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">
                            <i class="bi bi-speedometer2 me-1"></i>Buka Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Masuk untuk Meminjam
                        </a>
                        <span class="text-muted small ms-2">
                            Belum punya akun? <a href="{{ route('register') }}">Daftar anggota</a>
                        </span>
                    @endauth
                </div>

                <table class="table detail-table">
                    <tbody>
                        <tr><th>ISBN</th><td>{{ $book->isbn ?: '-' }}</td></tr>
                        <tr><th>Penerbit</th>
                            <td><a href="{{ route('catalog.index', ['publisher' => $book->publisher_id]) }}">{{ $book->publisher->name }}</a></td></tr>
                        <tr><th>Tahun Terbit</th><td>{{ $book->publication_year ?: '-' }}</td></tr>
                        <tr><th>Bahasa</th><td>{{ $book->language }}</td></tr>
                        <tr><th>Edisi</th><td>{{ $book->edition ?: '-' }}</td></tr>
                        <tr><th>Jumlah Halaman</th><td>{{ $book->pages ?: '-' }}</td></tr>
                        <tr><th>Lokasi</th>
                            <td>
                                @if ($book->shelf)
                                    {{ $book->shelf->name }} ({{ $book->shelf->code }})@if ($book->shelf_number), nomor {{ $book->shelf_number }}@endif
                                    @if ($book->shelf->location) <span class="text-muted">· {{ $book->shelf->location }}</span>@endif
                                @else
                                    -
                                @endif
                            </td></tr>
                    </tbody>
                </table>

                @if ($book->description)
                    <h2 class="h5 mt-4">Deskripsi</h2>
                    <p>{!! nl2br(e($book->description)) !!}</p>
                @endif
            </div>
        </div>

        @if ($related->isNotEmpty())
            <hr class="my-5">
            <h2 class="section-title">Buku Sekategori</h2>
            <div class="row g-4 mt-1">
                @foreach ($related as $item)
                    <div class="col-6 col-lg-3">
                        <x-book-card :book="$item->setRelation('category', $book->category)" />
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
