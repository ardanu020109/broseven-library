@extends('layouts.anggota')

@use('App\Support\Format')

@section('title', 'Denda Saya')
@section('page_title', 'Denda Saya')

@section('content')
<x-page-header title="Denda Saya" subtitle="Denda keterlambatan pengembalian buku. Pembayaran dilakukan di perpustakaan." />

<div class="row g-3 mb-4">
    <div class="col-md-5 col-xl-4">
        <x-stat-card icon="bi-cash-coin" label="Denda Belum Dibayar" :value="Format::rupiah($unpaidTotal)" :tone="$unpaidTotal > 0 ? 'danger' : 'sienna'"
                     :hint="$unpaidTotal > 0 ? 'Silakan bayar di loket petugas' : 'Tidak ada tunggakan'" />
    </div>
</div>

<div class="card dash-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th>Tanggal</th><th>Buku</th><th class="text-center">Terlambat</th><th class="text-end">Denda</th><th class="text-end">Dibayar</th><th class="text-end">Sisa</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($fines as $fine)
                    <tr>
                        <td class="text-nowrap small">{{ $fine->created_at->translatedFormat('d M Y') }}</td>
                        <td>
                            {{ $fine->returnItem->borrowingItem->book?->title ?? '(dihapus)' }}
                            <div class="small text-muted">{{ $fine->returnItem->returnTransaction->borrowing->code }}</div>
                            @if ($fine->payments->isNotEmpty())
                                <details class="small mt-1">
                                    <summary class="text-sienna">Riwayat pembayaran</summary>
                                    <ul class="mb-0 ps-3">
                                        @foreach ($fine->payments as $payment)
                                            <li>
                                                {{ $payment->paid_at->translatedFormat('d M Y') }}:
                                                {{ $payment->type === 'waiver' ? 'dibebaskan' : 'bayar' }} {{ Format::rupiah($payment->amount) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </td>
                        <td class="text-center">{{ $fine->returnItem->days_late }} hari</td>
                        <td class="text-end">{{ Format::rupiah($fine->amount) }}</td>
                        <td class="text-end">{{ Format::rupiah($fine->paid_amount) }}</td>
                        <td class="text-end fw-semibold">{{ $fine->status->value === 'unpaid' ? Format::rupiah($fine->remaining_amount) : '-' }}</td>
                        <td><span class="badge badge-status {{ $fine->status->badgeClass() }}">{{ $fine->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-note">Anda tidak memiliki denda. Terima kasih sudah mengembalikan buku tepat waktu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($fines->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $fines->links() }}</div>
    @endif
</div>
@endsection
