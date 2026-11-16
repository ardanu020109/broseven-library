@extends('layouts.super-admin')

@use('App\Support\ActivityLabel')
@use('App\Support\Format')

@section('title', 'Dashboard Super Admin')
@section('page_title', 'Dashboard')

@section('content')
<x-page-header title="Dashboard Super Admin"
               subtitle="Ringkasan seluruh aktivitas perpustakaan hingga {{ now()->translatedFormat('d F Y') }}." />

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-journal-bookmark" label="Total Buku" :value="Format::number($stats['books'])" hint="judul terdaftar" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-collection" label="Total Eksemplar" :value="Format::number($stats['copies'])" tone="sienna" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-check2-square" label="Stok Tersedia" :value="Format::number($stats['available'])" tone="sienna" hint="siap dipinjam" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-people" label="Total Anggota" :value="Format::number($stats['members'])" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-person-gear" label="Total Admin" :value="Format::number($stats['admins'])" tone="dark" hint="semua jenis admin" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-journal-arrow-up" label="Peminjaman Aktif" :value="Format::number($stats['active_loans'])" tone="dark" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-journal-arrow-down" label="Total Pengembalian" :value="Format::number($stats['returns'])" tone="beige" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-exclamation-triangle" label="Buku Terlambat" :value="Format::number($stats['overdue_books'])" tone="danger" hint="eksemplar belum kembali" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-cash-coin" label="Total Denda" :value="Format::rupiah($stats['fines_total'])" tone="beige" :hint="'Belum dibayar ' . Format::rupiah($stats['fines_unpaid'])" /></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-bar-chart me-2"></i>Peminjaman Bulanan</div>
            <div class="card-body">
                <div class="chart-box">
                    <canvas data-chart="{{ json_encode($charts['borrowings']) }}" data-chart-type="bar" data-chart-color="crimson"
                            role="img" aria-label="Grafik peminjaman 12 bulan terakhir"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-graph-up me-2"></i>Pengembalian Bulanan</div>
            <div class="card-body">
                <div class="chart-box">
                    <canvas data-chart="{{ json_encode($charts['returns']) }}" data-chart-type="line" data-chart-color="sienna"
                            role="img" aria-label="Grafik pengembalian 12 bulan terakhir"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-trophy me-2"></i>Buku Paling Populer</div>
            <div class="card-body p-0">
                @forelse ($popularBooks as $book)
                    <div class="list-row">
                        <span class="rank-badge">{{ $loop->iteration }}</span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $book->title }}</div>
                            <div class="small text-muted">{{ $book->author->name }}</div>
                        </div>
                        <span class="badge badge-status badge-borrowed">{{ $book->borrowed_total }}x dipinjam</span>
                    </div>
                @empty
                    <p class="empty-note">Belum ada data peminjaman.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-activity me-2"></i>Aktivitas Terbaru</div>
            <div class="card-body p-0">
                @forelse ($activities as $log)
                    @php
                        [$label, $icon, $tone] = ActivityLabel::describe($log->action);
                    @endphp
                    <div class="list-row align-items-start">
                        <span class="activity-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                        <div class="flex-grow-1">
                            <div>
                                <strong>{{ $label }}</strong>
                                <span class="text-muted">oleh {{ $log->user?->name ?? 'Tamu' }}</span>
                            </div>
                            @if ($log->description)
                                <div class="small text-muted">{{ $log->description }}</div>
                            @endif
                        </div>
                        <time class="small text-muted text-nowrap" datetime="{{ $log->created_at->toIso8601String() }}">
                            {{ $log->created_at->diffForHumans() }}
                        </time>
                    </div>
                @empty
                    <p class="empty-note">Belum ada aktivitas tercatat.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/chart.umd.js') }}"></script>
    <script src="{{ asset('assets/js/dashboard-charts.js') }}"></script>
@endpush
