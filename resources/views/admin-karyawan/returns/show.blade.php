@extends(\App\Support\Panel::layout())

@use('App\Support\Format')
@use('App\Support\Panel')

@section('title', 'Pengembalian ' . $returnTransaction->code)
@section('page_title', 'Pengembalian')

@section('content')
@php
    $borrowing = $returnTransaction->borrowing;
    $totalFine = $returnTransaction->items->sum('fine_amount');
@endphp

<x-page-header title="Detail Pengembalian" :subtitle="$returnTransaction->code">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ Panel::route('returns.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <a href="{{ Panel::route('borrowings.show', $borrowing) }}" class="btn btn-outline-secondary"><i class="bi bi-journal-text me-1"></i>Peminjaman</a>
        <a href="{{ Panel::route('returns.receipt', $returnTransaction) }}" target="_blank" rel="noopener" class="btn btn-primary">
            <i class="bi bi-printer me-1"></i>Cetak Bukti (PDF)
        </a>
    </div>
</x-page-header>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-journal-arrow-down me-2"></i>Informasi Pengembalian</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-sienna">Kode</dt><dd class="col-sm-8">{{ $returnTransaction->code }}</dd>
                    <dt class="col-sm-4 text-sienna">Tanggal kembali</dt><dd class="col-sm-8">{{ $returnTransaction->return_date->translatedFormat('d F Y') }}</dd>
                    <dt class="col-sm-4 text-sienna">Peminjaman</dt><dd class="col-sm-8">{{ $borrowing->code }}</dd>
                    <dt class="col-sm-4 text-sienna">Anggota</dt>
                    <dd class="col-sm-8">{{ $borrowing->member->name }} <span class="text-muted">({{ $borrowing->member->memberProfile?->member_number ?? '-' }})</span></dd>
                    <dt class="col-sm-4 text-sienna">Petugas</dt><dd class="col-sm-8">{{ $returnTransaction->processor?->name ?? '-' }}</dd>
                    <dt class="col-sm-4 text-sienna">Catatan</dt><dd class="col-sm-8">{{ $returnTransaction->notes ?: '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <x-stat-card icon="bi-cash-coin" label="Total Denda Pengembalian Ini" :value="Format::rupiah($totalFine)" tone="beige" />
    </div>
</div>

<div class="card dash-card">
    <div class="card-header dash-card-header"><i class="bi bi-journal-bookmark me-2"></i>Buku yang Dikembalikan</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Buku</th><th class="text-center">Jumlah</th><th>Jatuh Tempo</th><th class="text-center">Hari Terlambat</th><th class="text-end">Tarif / Hari</th><th class="text-end">Denda</th><th>Status Denda</th></tr>
            </thead>
            <tbody>
                @foreach ($returnTransaction->items as $item)
                    <tr>
                        <td>{{ $item->borrowingItem->book?->title ?? '(buku dihapus)' }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-nowrap small">{{ $item->due_date->translatedFormat('d M Y') }}</td>
                        <td class="text-center">{{ $item->days_late }}</td>
                        <td class="text-end">{{ Format::rupiah($item->fine_per_day) }}</td>
                        <td class="text-end fw-semibold">{{ Format::rupiah($item->fine_amount) }}</td>
                        <td>
                            @if ($item->fine)
                                <a href="{{ Panel::route('fines.show', $item->fine) }}" class="text-decoration-none">
                                    <span class="badge badge-status {{ $item->fine->status->badgeClass() }}">{{ $item->fine->status->label() }}</span>
                                </a>
                            @else
                                <span class="text-muted small">Tanpa denda</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
