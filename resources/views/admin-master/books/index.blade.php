@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', 'Data Buku')
@section('page_title', 'Buku')

@section('content')
<x-page-header title="Data Buku" :subtitle="$books->total() . ' buku ditemukan'">
    <a href="{{ Panel::route('books.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Tambah Buku
    </a>
</x-page-header>

<div class="card dash-card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ Panel::route('books.index') }}" role="search">
            <div class="row g-2">
                <div class="col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control"
                               placeholder="Cari judul atau ISBN..." aria-label="Cari buku">
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <select name="category" class="form-select" aria-label="Kategori" data-autosubmit>
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}" @selected($filters['category'] === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <select name="author" class="form-select" aria-label="Penulis" data-autosubmit>
                        <option value="">Semua penulis</option>
                        @foreach ($authors as $id => $name)
                            <option value="{{ $id }}" @selected($filters['author'] === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <select name="publisher" class="form-select" aria-label="Penerbit" data-autosubmit>
                        <option value="">Semua penerbit</option>
                        @foreach ($publishers as $id => $name)
                            <option value="{{ $id }}" @selected($filters['publisher'] === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <select name="availability" class="form-select" aria-label="Ketersediaan" data-autosubmit>
                        <option value="">Semua stok</option>
                        <option value="available" @selected($filters['availability'] === 'available')>Tersedia</option>
                        <option value="low" @selected($filters['availability'] === 'low')>Stok menipis</option>
                        <option value="empty" @selected($filters['availability'] === 'empty')>Habis</option>
                    </select>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-2">
                <select name="status" class="form-select form-select-sm w-auto" aria-label="Status" data-autosubmit>
                    <option value="">Semua status</option>
                    <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                @if ($hasFilters)
                    <a href="{{ Panel::route('books.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 3.5rem">No</th>
                    <th>Buku</th>
                    <th>Kategori</th>
                    <th>Penulis</th>
                    <th>Penerbit</th>
                    <th class="text-center">Stok</th>
                    <th class="text-end" style="width: 9rem">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($books as $book)
                    <tr>
                        <td>{{ $books->firstItem() + $loop->index }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{ $book->cover_url }}" alt="" class="thumb">
                                <div>
                                    <a href="{{ Panel::route('books.show', $book) }}" class="fw-semibold text-decoration-none">{{ $book->title }}</a>
                                    <div class="small text-muted">{{ $book->isbn ?: 'Tanpa ISBN' }}</div>
                                    @if ($book->status === 'inactive')
                                        <span class="badge text-bg-secondary">Nonaktif</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>{{ $book->category->name }}</td>
                        <td>{{ $book->author->name }}</td>
                        <td>{{ $book->publisher->name }}</td>
                        <td class="text-center text-nowrap">
                            @php
                                $stockClass = $book->available_stock === 0
                                    ? 'badge-overdue'
                                    : ($book->available_stock <= $threshold ? 'badge-pending' : 'badge-available');
                            @endphp
                            <span class="badge badge-status {{ $stockClass }}" title="Tersedia / total">{{ $book->available_stock }} / {{ $book->total_stock }}</span>
                            @if ($book->borrowed_stock > 0)
                                <div class="small text-muted">{{ $book->borrowed_stock }} dipinjam</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ Panel::route('books.show', $book) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail"><i class="bi bi-eye"></i></a>
                            <a href="{{ Panel::route('books.edit', $book) }}" class="btn btn-sm btn-outline-primary" title="Ubah" aria-label="Ubah"><i class="bi bi-pencil"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" aria-label="Hapus"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-delete-url="{{ Panel::route('books.destroy', $book) }}"
                                    data-delete-name="{{ $book->title }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-note">
                            {{ $hasFilters ? 'Tidak ada buku yang cocok dengan filter.' : 'Belum ada buku.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($books->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $books->links() }}
        </div>
    @endif
</div>

<x-delete-modal />
@endsection
