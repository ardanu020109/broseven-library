@extends(\App\Support\Panel::layout())

@use('App\Support\Format')
@use('App\Support\Panel')

@section('title', 'Pengembalian')
@section('page_title', 'Pengembalian')

@section('content')
<x-page-header title="Pengembalian Buku" subtitle="Cari kode peminjaman atau nama anggota, lalu proses pengembalian." />

<div class="card dash-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ Panel::route('returns.index') }}" class="row g-2" role="search">
            <div class="col-md-8 col-lg-6">
                <div class="input-group input-group-lg">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ $term }}" maxlength="100" class="form-control" autofocus
                           placeholder="Kode peminjaman, nama anggota, atau nomor anggota" aria-label="Cari peminjaman">
                    <button type="submit" class="btn btn-primary">Cari</button>
                </div>
            </div>
            @if ($term !== '')
                <div class="col-auto"><a href="{{ Panel::route('returns.index') }}" class="btn btn-lg btn-outline-secondary">Reset</a></div>
            @endif
        </form>
    </div>
</div>

<div class="card dash-card mb-4">
    <div class="card-header dash-card-header"><i class="bi bi-book-half me-2"></i>Peminjaman yang Masih Berjalan</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Kode</th><th>Anggota</th><th>Buku Belum Kembali</th><th>Jatuh Tempo</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                @forelse ($borrowings as $borrowing)
                    @php
                        $outstanding = $borrowing->items->filter(fn ($i) => $i->outstanding_quantity > 0);
                    @endphp
                    <tr>
                        <td class="text-nowrap fw-semibold">{{ $borrowing->code }}</td>
                        <td>
                            {{ $borrowing->member->name }}
                            <div class="small text-muted">{{ $borrowing->member->memberProfile?->member_number ?? '-' }}</div>
                        </td>
                        <td>
                            <div class="small">{{ $outstanding->map(fn ($i) => $i->book?->title ?? '(dihapus)')->join(', ') }}</div>
                            <div class="small text-muted">{{ $outstanding->sum('outstanding_quantity') }} eksemplar</div>
                        </td>
                        <td class="text-nowrap small">
                            {{ $borrowing->due_date?->translatedFormat('d M Y') ?? '-' }}
                            @if ($borrowing->is_overdue)
                                <div class="text-danger">Lewat {{ abs($borrowing->days_remaining) }} hari</div>
                            @endif
                        </td>
                        <td><x-borrowing-status :borrowing="$borrowing" /></td>
                        <td class="text-end">
                            <a href="{{ Panel::route('returns.create', $borrowing) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-journal-arrow-down me-1"></i>Proses Pengembalian
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-note">{{ $term !== '' ? 'Tidak ada peminjaman berjalan yang cocok dengan pencarian.' : 'Tidak ada buku yang sedang dipinjam.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($borrowings->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $borrowings->links() }}</div>
    @endif
</div>

<div class="card dash-card">
    <div class="card-header dash-card-header"><i class="bi bi-clock-history me-2"></i>Pengembalian Terakhir</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Kode</th><th>Peminjaman</th><th>Anggota</th><th>Tanggal</th><th class="text-center">Buku</th><th class="text-end">Denda</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                @forelse ($recentReturns as $return)
                    <tr>
                        <td class="fw-semibold">{{ $return->code }}</td>
                        <td class="text-nowrap">{{ $return->borrowing->code }}</td>
                        <td>{{ $return->borrowing->member->name }}</td>
                        <td class="text-nowrap small">{{ $return->return_date->translatedFormat('d M Y') }}</td>
                        <td class="text-center">{{ (int) $return->total_quantity }}</td>
                        <td class="text-end">{{ Format::rupiah($return->total_fine) }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ Panel::route('returns.show', $return) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail"><i class="bi bi-eye"></i></a>
                            <a href="{{ Panel::route('returns.receipt', $return) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Cetak bukti" aria-label="Cetak bukti"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-note">Belum ada pengembalian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
