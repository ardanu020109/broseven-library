@extends('layouts.admin-master')

@use('App\Support\Format')

@section('title', 'Dashboard Admin Master')
@section('page_title', 'Dashboard')

@section('content')
<x-page-header title="Dashboard Admin Master" subtitle="Ringkasan data master buku dan stok." />

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-journal-bookmark" label="Total Buku" :value="Format::number($stats['books'])" hint="judul terdaftar" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-tags" label="Total Kategori" :value="Format::number($stats['categories'])" tone="sienna" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-person-lines-fill" label="Total Penulis" :value="Format::number($stats['authors'])" tone="sienna" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-building" label="Total Penerbit" :value="Format::number($stats['publishers'])" tone="dark" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-collection" label="Total Stok Buku" :value="Format::number($stats['copies'])" tone="dark" :hint="'Tersedia ' . Format::number($stats['available'])" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-exclamation-diamond" label="Buku Stok Menipis" :value="Format::number($stats['low_stock'])" tone="danger" :hint="'Tersedia ≤ ' . $threshold . ' eksemplar'" /></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-exclamation-diamond me-2"></i>Buku Stok Menipis</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Judul</th><th class="text-center">Tersedia</th><th class="text-center">Total</th></tr></thead>
                    <tbody>
                        @forelse ($lowStockBooks as $book)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $book->title }}</div>
                                    <div class="small text-muted">{{ $book->author->name }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-status {{ $book->available_stock === 0 ? 'badge-overdue' : 'badge-pending' }}">{{ $book->available_stock }}</span>
                                </td>
                                <td class="text-center">{{ $book->total_stock }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-note">Semua buku memiliki stok yang cukup.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-stars me-2"></i>Buku Terbaru</div>
            <div class="card-body p-0">
                @forelse ($latestBooks as $book)
                    <div class="list-row">
                        <img src="{{ $book->cover_url }}" alt="" class="thumb">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $book->title }}</div>
                            <div class="small text-muted">{{ $book->author->name }} · {{ $book->category->name }}</div>
                        </div>
                        <span class="small text-muted text-nowrap">{{ $book->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="empty-note">Belum ada buku.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
