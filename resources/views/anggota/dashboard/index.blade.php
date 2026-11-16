@extends('layouts.anggota')

@use('App\Support\Format')

@section('title', 'Dashboard Anggota')
@section('page_title', 'Dashboard')

@section('content')
    <x-page-header title="Halo, {{ auth()->user()->name }}" subtitle="Ringkasan peminjaman dan keanggotaan Anda." />

    @if ($stats['overdue'] > 0)
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-octagon fs-4 me-3"></i>
            <div>
                Anda memiliki <strong>{{ $stats['overdue'] }}</strong> peminjaman yang sudah melewati jatuh tempo.
                Segera kembalikan buku ke perpustakaan untuk menghindari denda bertambah.
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-4"><x-stat-card icon="bi-book-half" label="Sedang Dipinjam" :value="Format::number($stats['active_books'])"
                hint="eksemplar buku" /></div>
        <div class="col-6 col-xl-4"><x-stat-card icon="bi-clock-history" label="Riwayat Peminjaman" :value="Format::number($stats['history'])"
                tone="sienna" hint="total transaksi" /></div>
        <div class="col-6 col-xl-4"><x-stat-card icon="bi-calendar-event" label="Mendekati Jatuh Tempo" :value="Format::number($stats['due_soon'])"
                tone="dark" :hint="'dalam ' . $dueSoonDays . ' hari'" /></div>
        <div class="col-6 col-xl-4"><x-stat-card icon="bi-exclamation-triangle" label="Terlambat" :value="Format::number($stats['overdue'])"
                tone="danger" hint="peminjaman" /></div>
        <div class="col-6 col-xl-4"><x-stat-card icon="bi-cash-coin" label="Denda Belum Dibayar" :value="Format::rupiah($stats['fines_unpaid'])"
                tone="beige" /></div>
        <div class="col-6 col-xl-4">
            <x-stat-card icon="bi-patch-check" label="Status Keanggotaan" :value="$membership['label']" :tone="$membership['valid'] ? 'sienna' : 'danger'"
                :hint="$membership['expired_at']
                    ? 'Berlaku s.d. ' . $membership['expired_at']->translatedFormat('d M Y')
                    : null" />
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="member-card-mini">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="mc-lib">{{ $libraryProfile->name }}</div>
                        <div class="mc-title">KARTU ANGGOTA</div>
                    </div>
                    <i class="bi bi-book-half mc-icon"></i>
                </div>

                <div class="d-flex align-items-center gap-3 mt-4">
                    <img src="{{ auth()->user()->photo_url }}" alt="" class="mc-photo">
                    <div>
                        <div class="mc-name">{{ auth()->user()->name }}</div>
                        <div class="mc-number">{{ $membership['number'] ?? '-' }}</div>
                        <div class="mc-meta">
                            @if ($membership['joined_at'])
                                Bergabung {{ $membership['joined_at']->translatedFormat('d M Y') }}
                            @endif
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-end mt-4">
                    <span class="mc-meta">
                        @if ($membership['expired_at'])
                            Berlaku s.d. {{ $membership['expired_at']->translatedFormat('d M Y') }}
                        @endif
                    </span>
                    <span
                        class="badge {{ $membership['valid'] ? 'bg-light text-dark' : 'bg-danger' }}">{{ $membership['label'] }}</span>
                </div>
                <div class="mt-3">
                    <a href="{{ route('anggota.membership-card.show') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-person-vcard me-1"></i>Lihat, Unduh, dan Cetak Kartu
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header"><i class="bi bi-calendar-event me-2"></i>Mendekati Jatuh Tempo
                </div>
                <div class="card-body p-0">
                    @forelse ($dueSoon as $borrowing)
                        <div class="list-row align-items-start">
                            <span class="activity-icon text-warning"><i class="bi bi-alarm"></i></span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">
                                    {{ $borrowing->items->map(fn($item) => $item->book->title)->join(', ') }}</div>
                                <div class="small text-muted">{{ $borrowing->code }} · jatuh tempo
                                    {{ $borrowing->due_date->translatedFormat('d M Y') }}</div>
                            </div>
                            <span class="badge badge-status badge-pending text-nowrap">
                                {{ $borrowing->days_remaining === 0 ? 'Hari ini' : $borrowing->days_remaining . ' hari lagi' }}
                            </span>
                        </div>
                    @empty
                        <p class="empty-note">Tidak ada buku yang mendekati jatuh tempo.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card dash-card">
        <div class="card-header dash-card-header"><i class="bi bi-clock-history me-2"></i>Riwayat Peminjaman Terbaru</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Buku</th>
                        <th>Diajukan</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentBorrowings as $borrowing)
                        <tr>
                            <td class="text-nowrap">{{ $borrowing->code }}</td>
                            <td>{{ $borrowing->items->map(fn($item) => $item->book->title)->join(', ') }}</td>
                            <td class="text-nowrap">{{ $borrowing->requested_at->translatedFormat('d M Y') }}</td>
                            <td class="text-nowrap">{{ $borrowing->due_date?->translatedFormat('d M Y') ?? '-' }}</td>
                            <td><x-borrowing-status :borrowing="$borrowing" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-note">Anda belum pernah meminjam buku.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
