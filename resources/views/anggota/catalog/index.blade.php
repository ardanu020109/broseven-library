@extends('layouts.anggota')

@section('title', 'Katalog Buku')
@section('page_title', 'Katalog Buku')

@section('content')
<x-page-header title="Katalog Buku" subtitle="Pilih buku, lalu ajukan peminjaman. Persetujuan dilakukan oleh petugas perpustakaan." />

@if ($problem)
    <div class="alert alert-warning d-flex" role="alert">
        <i class="bi bi-exclamation-triangle fs-5 me-3"></i>
        <div><strong>Anda belum dapat mengajukan peminjaman.</strong><br>{{ $problem }}</div>
    </div>
@endif

<div class="card dash-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('anggota.catalog.index') }}" class="row g-2" role="search">
            <div class="col-lg-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control"
                           placeholder="Judul atau ISBN..." aria-label="Cari buku">
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <select name="category" class="form-select" aria-label="Kategori" data-autosubmit>
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <select name="availability" class="form-select" aria-label="Ketersediaan" data-autosubmit>
                    <option value="">Semua stok</option>
                    <option value="available" @selected($filters['availability'] === 'available')>Tersedia</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                @if ($hasFilters)<a href="{{ route('anggota.catalog.index') }}" class="btn btn-outline-secondary">Reset</a>@endif
            </div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('anggota.borrowings.store') }}" id="borrowForm" data-once
      data-max="{{ $max }}" data-blocked="{{ $problem ? '1' : '0' }}">
    @csrf

    <div class="row g-4">
        @forelse ($books as $book)
            @php
                $reason = match (true) {
                    $book->available_stock < 1 => 'Sedang dipinjam semua',
                    $engaged->contains($book->id) => 'Sudah Anda pinjam/ajukan',
                    (bool) $problem => 'Tidak dapat dipinjam',
                    default => null,
                };
            @endphp
            <div class="col-6 col-md-4 col-xl-3">
                <x-borrow-card :book="$book" :reason="$reason" />
            </div>
        @empty
            <div class="col-12"><p class="empty-note">Tidak ada buku yang cocok.</p></div>
        @endforelse
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mt-4">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mt-4 d-flex justify-content-center">{{ $books->links() }}</div>

    <div class="borrow-bar">
        <div class="row g-2 align-items-center">
            <div class="col-md-3 fw-semibold">
                <i class="bi bi-bag-check me-1 text-crimson"></i>
                <span id="selectedCount">0</span> dari maks. {{ $max }} buku dipilih
            </div>
            <div class="col-md-6">
                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="255" class="form-control form-control-sm"
                       placeholder="Catatan untuk petugas (opsional)" aria-label="Catatan">
            </div>
            <div class="col-md-3 text-md-end">
                <button type="submit" id="borrowSubmit" class="btn btn-primary" disabled>
                    <i class="bi bi-send me-1"></i>Ajukan Peminjaman
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/borrow-select.js') }}"></script>
@endpush
