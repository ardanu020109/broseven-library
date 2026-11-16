@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', 'Peminjaman')
@section('page_title', 'Peminjaman')

@section('content')
<x-page-header title="Data Peminjaman" :subtitle="$borrowings->total() . ' transaksi ditemukan'">
    <a href="{{ Panel::route('borrowings.active') }}" class="btn btn-outline-primary"><i class="bi bi-book-half me-1"></i>Buku Dipinjam</a>
</x-page-header>

<div class="card dash-card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ Panel::route('borrowings.index') }}" class="row g-2" role="search">
            <div class="col-lg-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control"
                           placeholder="Kode, nama anggota, atau judul buku..." aria-label="Cari">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <select name="status" class="form-select" aria-label="Status" data-autosubmit>
                    <option value="">Semua status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2"><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control" aria-label="Diajukan dari"></div>
            <div class="col-6 col-lg-2"><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control" aria-label="Diajukan sampai"></div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                @if ($hasFilters)<a href="{{ Panel::route('borrowings.index') }}" class="btn btn-outline-secondary">Reset</a>@endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Kode</th><th>Anggota</th><th>Buku</th><th>Diajukan</th><th>Jatuh Tempo</th><th>Status</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($borrowings as $borrowing)
                    <tr>
                        <td class="text-nowrap fw-semibold">{{ $borrowing->code }}</td>
                        <td>{{ $borrowing->member->name }}</td>
                        <td>
                            <div class="small">{{ $borrowing->items->map(fn ($i) => $i->book?->title ?? '(dihapus)')->take(2)->join(', ') }}</div>
                            @if ($borrowing->items->count() > 2)
                                <div class="small text-muted">+{{ $borrowing->items->count() - 2 }} lainnya</div>
                            @endif
                        </td>
                        <td class="text-nowrap small">{{ $borrowing->requested_at->translatedFormat('d M Y H:i') }}</td>
                        <td class="text-nowrap small">{{ $borrowing->due_date?->translatedFormat('d M Y') ?? '-' }}</td>
                        <td><x-borrowing-status :borrowing="$borrowing" /></td>
                        <td class="text-end text-nowrap">
                            @if ($borrowing->status->value === 'pending')
                                <a href="{{ Panel::route('borrowings.show', $borrowing) }}" class="btn btn-sm btn-primary"><i class="bi bi-check2-square me-1"></i>Proses</a>
                            @else
                                <a href="{{ Panel::route('borrowings.show', $borrowing) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail"><i class="bi bi-eye"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-note">{{ $hasFilters ? 'Tidak ada transaksi yang cocok dengan filter.' : 'Belum ada transaksi peminjaman.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($borrowings->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $borrowings->links() }}</div>
    @endif
</div>
@endsection
