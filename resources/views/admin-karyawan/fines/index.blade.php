@extends(\App\Support\Panel::layout())

@use('App\Support\Format')
@use('App\Support\Panel')

@section('title', 'Denda')
@section('page_title', 'Denda')

@section('content')
<x-page-header title="Data Denda" :subtitle="$fines->total() . ' denda ditemukan'" />

<div class="row g-3 mb-4">
    <div class="col-md-4"><x-stat-card icon="bi-cash-coin" label="Denda Belum Dibayar" :value="Format::rupiah($summary['unpaid_total'])" tone="danger" :hint="$summary['unpaid_count'] . ' denda belum lunas'" /></div>
    <div class="col-md-4"><x-stat-card icon="bi-wallet2" label="Total Pembayaran Diterima" :value="Format::rupiah($summary['collected'])" tone="sienna" /></div>
</div>

<div class="card dash-card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ Panel::route('fines.index') }}" class="row g-2" role="search">
            <div class="col-lg-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control"
                           placeholder="Nama anggota atau kode..." aria-label="Cari denda">
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
            <div class="col-6 col-lg-2"><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control" aria-label="Dari tanggal"></div>
            <div class="col-6 col-lg-2"><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control" aria-label="Sampai tanggal"></div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                @if ($hasFilters)<a href="{{ Panel::route('fines.index') }}" class="btn btn-outline-secondary">Reset</a>@endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Tanggal</th><th>Anggota</th><th>Buku</th><th class="text-center">Terlambat</th><th class="text-end">Denda</th><th class="text-end">Dibayar</th><th class="text-end">Sisa</th><th>Status</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($fines as $fine)
                    <tr>
                        <td class="text-nowrap small">{{ $fine->created_at->translatedFormat('d M Y') }}</td>
                        <td>
                            {{ $fine->member->name }}
                            <div class="small text-muted">{{ $fine->returnItem->returnTransaction->borrowing->code }}</div>
                        </td>
                        <td>{{ $fine->returnItem->borrowingItem->book?->title ?? '(dihapus)' }}</td>
                        <td class="text-center">{{ $fine->returnItem->days_late }} hari</td>
                        <td class="text-end">{{ Format::rupiah($fine->amount) }}</td>
                        <td class="text-end">{{ Format::rupiah($fine->paid_amount) }}</td>
                        <td class="text-end fw-semibold">{{ $fine->status->value === 'unpaid' ? Format::rupiah($fine->remaining_amount) : '-' }}</td>
                        <td><span class="badge badge-status {{ $fine->status->badgeClass() }}">{{ $fine->status->label() }}</span></td>
                        <td class="text-end">
                            <a href="{{ Panel::route('fines.show', $fine) }}"
                               class="btn btn-sm {{ $fine->status->value === 'unpaid' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                {{ $fine->status->value === 'unpaid' ? 'Proses' : 'Detail' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-note">{{ $hasFilters ? 'Tidak ada denda yang cocok dengan filter.' : 'Belum ada denda.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($fines->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $fines->links() }}</div>
    @endif
</div>
@endsection
