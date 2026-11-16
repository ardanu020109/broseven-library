@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', $book->title)
@section('page_title', 'Buku')

@section('content')
@php
    $typeLabels = ['initial' => 'Stok awal', 'adjustment' => 'Penyesuaian', 'borrow' => 'Peminjaman', 'return' => 'Pengembalian'];
    $stockClass = $book->available_stock === 0 ? 'badge-overdue' : ($book->available_stock <= $threshold ? 'badge-pending' : 'badge-available');
@endphp

<x-page-header title="Detail Buku" :subtitle="$book->title">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ Panel::route('books.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        @if ($book->status === 'active')
            <a href="{{ route('catalog.show', $book) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-up-right me-1"></i>Lihat di Katalog
            </a>
        @endif
        <a href="{{ Panel::route('books.edit', $book) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Ubah</a>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                data-delete-url="{{ Panel::route('books.destroy', $book) }}" data-delete-name="{{ $book->title }}">
            <i class="bi bi-trash me-1"></i>Hapus
        </button>
    </div>
</x-page-header>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-journal-bookmark me-2"></i>Informasi Buku</div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4 text-center">
                        <img src="{{ $book->cover_url }}" alt="Sampul {{ $book->title }}" class="cover-large">
                        <div class="mt-2">
                            <span class="badge {{ $book->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $book->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-sienna">Judul</dt><dd class="col-sm-8">{{ $book->title }}</dd>
                            <dt class="col-sm-4 text-sienna">ISBN</dt><dd class="col-sm-8">{{ $book->isbn ?: '-' }}</dd>
                            <dt class="col-sm-4 text-sienna">Kategori</dt><dd class="col-sm-8">{{ $book->category->name }}</dd>
                            <dt class="col-sm-4 text-sienna">Penulis</dt><dd class="col-sm-8">{{ $book->author->name }}</dd>
                            <dt class="col-sm-4 text-sienna">Penerbit</dt><dd class="col-sm-8">{{ $book->publisher->name }}</dd>
                            <dt class="col-sm-4 text-sienna">Tahun Terbit</dt><dd class="col-sm-8">{{ $book->publication_year ?: '-' }}</dd>
                            <dt class="col-sm-4 text-sienna">Bahasa</dt><dd class="col-sm-8">{{ $book->language }}</dd>
                            <dt class="col-sm-4 text-sienna">Edisi</dt><dd class="col-sm-8">{{ $book->edition ?: '-' }}</dd>
                            <dt class="col-sm-4 text-sienna">Halaman</dt><dd class="col-sm-8">{{ $book->pages ?: '-' }}</dd>
                            <dt class="col-sm-4 text-sienna">Harga</dt>
                            <dd class="col-sm-8">{{ $book->price !== null ? \App\Support\Format::rupiah($book->price) : '-' }}</dd>
                            <dt class="col-sm-4 text-sienna">Lokasi</dt>
                            <dd class="col-sm-8">
                                @if ($book->shelf)
                                    {{ $book->shelf->code }} · {{ $book->shelf->name }}@if ($book->shelf_number), nomor {{ $book->shelf_number }}@endif
                                @else
                                    -
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>

                @if ($book->description)
                    <hr>
                    <h2 class="h6 text-sienna">Deskripsi</h2>
                    <p class="mb-0">{!! nl2br(e($book->description)) !!}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-collection me-2"></i>Stok</div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4"><div class="stock-pill"><div class="num">{{ $book->total_stock }}</div><div class="lbl">Total</div></div></div>
                    <div class="col-4"><div class="stock-pill"><div class="num text-success">{{ $book->available_stock }}</div><div class="lbl">Tersedia</div></div></div>
                    <div class="col-4"><div class="stock-pill"><div class="num text-sienna">{{ $book->borrowed_stock }}</div><div class="lbl">Dipinjam</div></div></div>
                </div>

                <p class="mb-2">
                    Status ketersediaan:
                    <span class="badge badge-status {{ $stockClass }}">
                        {{ $book->available_stock === 0 ? 'Habis' : ($book->available_stock <= $threshold ? 'Menipis' : 'Aman') }}
                    </span>
                </p>
                <p class="small text-muted mb-0">
                    Transaksi aktif untuk buku ini: <strong>{{ $openTransactions }}</strong>.
                    @if ($openTransactions > 0 || $book->borrowed_stock > 0)
                        Buku tidak dapat dihapus selama masih ada transaksi aktif.
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card dash-card">
    <div class="card-header dash-card-header"><i class="bi bi-clock-history me-2"></i>Riwayat Stok (15 terakhir)</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Waktu</th><th>Jenis</th><th class="text-center">Perubahan</th><th class="text-center">Total</th><th class="text-center">Tersedia</th><th class="text-center">Dipinjam</th><th>Oleh</th><th>Catatan</th></tr>
            </thead>
            <tbody>
                @forelse ($histories as $history)
                    <tr>
                        <td class="text-nowrap">{{ $history->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td>{{ $typeLabels[$history->type] ?? $history->type }}</td>
                        <td class="text-center fw-bold {{ $history->quantity_change >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $history->quantity_change > 0 ? '+' : '' }}{{ $history->quantity_change }}
                        </td>
                        <td class="text-center">{{ $history->total_after }}</td>
                        <td class="text-center">{{ $history->available_after }}</td>
                        <td class="text-center">{{ $history->borrowed_after }}</td>
                        <td>{{ $history->user?->name ?? 'Sistem' }}</td>
                        <td>{{ $history->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-note">Belum ada riwayat stok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-delete-modal />
@endsection
