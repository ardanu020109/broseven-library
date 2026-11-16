@extends('layouts.anggota')

@section('title', $book->title)
@section('page_title', 'Katalog Buku')

@section('content')
<x-page-header title="Detail Buku" :subtitle="$book->title">
    <a href="{{ route('anggota.catalog.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali ke Katalog</a>
</x-page-header>

@if ($problem)
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>Anda belum dapat mengajukan peminjaman.</strong> {{ $problem }}</div>
@endif

<div class="card dash-card">
    <div class="card-body p-4">
        <div class="row g-4">
            <div class="col-md-4 col-lg-3 text-center">
                <img src="{{ $book->cover_url }}" alt="Sampul {{ $book->title }}" class="cover-large">
            </div>

            <div class="col-md-8 col-lg-9">
                <span class="badge-category">{{ $book->category->name }}</span>
                <h2 class="h3 mt-2 mb-1">{{ $book->title }}</h2>
                <p class="text-muted">oleh {{ $book->author->name }}</p>

                <div class="row g-2 text-center mb-3" style="max-width:26rem">
                    <div class="col-4"><div class="stock-pill"><div class="num">{{ $book->total_stock }}</div><div class="lbl">Total</div></div></div>
                    <div class="col-4"><div class="stock-pill"><div class="num text-success">{{ $book->available_stock }}</div><div class="lbl">Tersedia</div></div></div>
                    <div class="col-4"><div class="stock-pill"><div class="num text-sienna">{{ $book->borrowed_stock }}</div><div class="lbl">Dipinjam</div></div></div>
                </div>

                @if ($engaged)
                    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Anda sudah meminjam atau mengajukan buku ini.
                        <a href="{{ route('anggota.borrowings.index') }}">Lihat peminjaman saya</a></div>
                @elseif ($book->available_stock < 1)
                    <div class="alert alert-secondary"><i class="bi bi-hourglass me-2"></i>Semua eksemplar sedang dipinjam. Coba lagi nanti.</div>
                @elseif (! $problem)
                    <form method="POST" action="{{ route('anggota.borrowings.store') }}" data-once class="mb-3">
                        @csrf
                        <input type="hidden" name="books[]" value="{{ $book->id }}">
                        <div class="row g-2" style="max-width:34rem">
                            <div class="col-sm-8">
                                <input type="text" name="notes" maxlength="255" class="form-control" placeholder="Catatan untuk petugas (opsional)" aria-label="Catatan">
                            </div>
                            <div class="col-sm-4">
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Ajukan</button>
                            </div>
                        </div>
                    </form>
                @endif

                <table class="table detail-table mb-0">
                    <tbody>
                        <tr><th class="text-sienna" style="width:11rem">ISBN</th><td>{{ $book->isbn ?: '-' }}</td></tr>
                        <tr><th class="text-sienna">Penerbit</th><td>{{ $book->publisher->name }}</td></tr>
                        <tr><th class="text-sienna">Tahun Terbit</th><td>{{ $book->publication_year ?: '-' }}</td></tr>
                        <tr><th class="text-sienna">Bahasa</th><td>{{ $book->language }}</td></tr>
                        <tr><th class="text-sienna">Halaman</th><td>{{ $book->pages ?: '-' }}</td></tr>
                        <tr><th class="text-sienna">Lokasi</th>
                            <td>@if ($book->shelf){{ $book->shelf->code }} · {{ $book->shelf->name }}@if ($book->shelf_number), nomor {{ $book->shelf_number }}@endif @else - @endif</td></tr>
                    </tbody>
                </table>

                @if ($book->description)
                    <h3 class="h6 text-sienna mt-4">Deskripsi</h3>
                    <p class="mb-0">{!! nl2br(e($book->description)) !!}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
