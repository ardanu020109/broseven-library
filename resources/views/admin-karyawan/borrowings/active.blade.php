@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', 'Buku Dipinjam')
@section('page_title', 'Buku Dipinjam')

@section('content')
<x-page-header title="Buku yang Sedang Dipinjam" :subtitle="$items->total() . ' judul belum dikembalikan'">
    <a href="{{ Panel::route('borrowings.index') }}" class="btn btn-outline-primary"><i class="bi bi-list-ul me-1"></i>Semua Peminjaman</a>
</x-page-header>

<div class="card dash-card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ Panel::route('borrowings.active') }}" class="row g-2" role="search">
            <div class="col-lg-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control"
                           placeholder="Kode, anggota, atau judul buku..." aria-label="Cari">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <select name="status" class="form-select" aria-label="Status" data-autosubmit>
                    <option value="">Semua status</option>
                    <option value="borrowed" @selected($filters['status'] === 'borrowed')>Dipinjam</option>
                    <option value="partially_returned" @selected($filters['status'] === 'partially_returned')>Sebagian dikembalikan</option>
                    <option value="overdue" @selected($filters['status'] === 'overdue')>Terlambat</option>
                </select>
            </div>
            <div class="col-6 col-lg-2"><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control" aria-label="Dipinjam dari"></div>
            <div class="col-6 col-lg-2"><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control" aria-label="Dipinjam sampai"></div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                @if ($hasFilters)<a href="{{ Panel::route('borrowings.active') }}" class="btn btn-outline-secondary">Reset</a>@endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Kode</th><th>Anggota</th><th>Judul Buku</th><th class="text-center">Jumlah</th><th>Tgl Pinjam</th><th>Jatuh Tempo</th><th>Sisa Hari</th><th>Status</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    @php
                        $borrowing = $item->borrowing;
                        $remaining = $borrowing->days_remaining;
                    @endphp
                    <tr>
                        <td class="text-nowrap fw-semibold">{{ $borrowing->code }}</td>
                        <td>{{ $borrowing->member->name }}</td>
                        <td>{{ $item->book?->title ?? '(buku dihapus)' }}</td>
                        <td class="text-center">{{ $item->outstanding_quantity }}</td>
                        <td class="text-nowrap small">{{ $borrowing->borrow_date?->translatedFormat('d M Y') ?? '-' }}</td>
                        <td class="text-nowrap small">{{ $borrowing->due_date?->translatedFormat('d M Y') ?? '-' }}</td>
                        <td class="text-nowrap">
                            @if ($remaining === null) -
                            @elseif ($remaining < 0) <span class="text-danger fw-semibold">Lewat {{ abs($remaining) }} hari</span>
                            @elseif ($remaining === 0) <span class="text-warning fw-semibold">Hari ini</span>
                            @else {{ $remaining }} hari
                            @endif
                        </td>
                        <td><x-borrowing-status :borrowing="$borrowing" /></td>
                        <td class="text-end">
                            <a href="{{ Panel::route('borrowings.show', $borrowing) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-note">{{ $hasFilters ? 'Tidak ada buku yang cocok dengan filter.' : 'Tidak ada buku yang sedang dipinjam.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $items->links() }}</div>
    @endif
</div>
@endsection
