@extends('layouts.admin-karyawan')

@use('App\Support\Format')

@section('title', 'Dashboard Admin Karyawan')
@section('page_title', 'Dashboard')

@section('content')
<x-page-header title="Dashboard Admin Karyawan"
               subtitle="Layanan operasional hari ini, {{ now()->translatedFormat('l, d F Y') }}." />

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-journal-arrow-up" label="Peminjaman Hari Ini" :value="Format::number($stats['loans_today'])" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-journal-arrow-down" label="Pengembalian Hari Ini" :value="Format::number($stats['returns_today'])" tone="sienna" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-hourglass-split" label="Menunggu Persetujuan" :value="Format::number($stats['pending'])" tone="dark" hint="pengajuan peminjaman" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-book-half" label="Buku Sedang Dipinjam" :value="Format::number($stats['borrowed_books'])" tone="sienna" hint="eksemplar" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-exclamation-triangle" label="Buku Terlambat" :value="Format::number($stats['overdue_books'])" tone="danger" hint="eksemplar belum kembali" /></div>
    <div class="col-6 col-xl-4"><x-stat-card icon="bi-cash-coin" label="Denda Belum Dibayar" :value="Format::rupiah($stats['fines_unpaid'])" tone="beige" /></div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-hourglass-split me-2"></i>Pengajuan Menunggu Persetujuan</div>
            <div class="card-body p-0">
                @forelse ($pendingRequests as $request)
                    <div class="list-row">
                        <span class="activity-icon text-warning"><i class="bi bi-person"></i></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">
                                <a href="{{ route('admin-karyawan.borrowings.show', $request) }}" class="text-decoration-none">{{ $request->member->name }}</a>
                            </div>
                            <div class="small text-muted">{{ $request->code }} · {{ (int) $request->total_items }} buku</div>
                        </div>
                        <span class="small text-muted text-nowrap">{{ $request->requested_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="empty-note">Tidak ada pengajuan yang menunggu.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-clock-history me-2"></i>Transaksi Terbaru</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Kode</th><th>Anggota</th><th class="text-center">Buku</th><th>Jatuh Tempo</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($recentBorrowings as $borrowing)
                            <tr>
                                <td class="text-nowrap">{{ $borrowing->code }}</td>
                                <td>{{ $borrowing->member->name }}</td>
                                <td class="text-center">{{ (int) $borrowing->total_items }}</td>
                                <td class="text-nowrap">{{ $borrowing->due_date?->translatedFormat('d M Y') ?? '-' }}</td>
                                <td><x-borrowing-status :borrowing="$borrowing" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-note">Belum ada transaksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
