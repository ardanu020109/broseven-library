@extends(\App\Support\Panel::layout())

@use('App\Support\Format')
@use('App\Support\Panel')

@section('title', 'Denda #' . $fine->id)
@section('page_title', 'Denda')

@section('content')
@php
    $isUnpaid = $fine->status->value === 'unpaid';
    $returnTx = $fine->returnItem->returnTransaction;
    $returnItem = $fine->returnItem;
@endphp

<x-page-header title="Detail Denda" :subtitle="$fine->member->name . ' · ' . $returnTx->borrowing->code">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ Panel::route('fines.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <a href="{{ Panel::route('returns.show', $returnTx) }}" class="btn btn-outline-secondary"><i class="bi bi-journal-arrow-down me-1"></i>Pengembalian</a>
    </div>
</x-page-header>

<div class="row g-3 mb-4">
    <div class="col-md-4"><x-stat-card icon="bi-cash-coin" label="Total Denda" :value="Format::rupiah($fine->amount)" tone="beige" /></div>
    <div class="col-md-4"><x-stat-card icon="bi-wallet2" label="Sudah Dibayar" :value="Format::rupiah($fine->paid_amount)" tone="sienna" /></div>
    <div class="col-md-4"><x-stat-card icon="bi-hourglass-split" label="Sisa" :value="$isUnpaid ? Format::rupiah($fine->remaining_amount) : Format::rupiah(0)" tone="danger" /></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-2"></i>Rincian</span>
                <span class="badge badge-status {{ $fine->status->badgeClass() }}">{{ $fine->status->label() }}</span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-sienna">Anggota</dt>
                    <dd class="col-sm-8"><a href="{{ Panel::route('members.show', $fine->member) }}" class="text-decoration-none">{{ $fine->member->name }}</a>
                        <span class="text-muted">({{ $fine->member->memberProfile?->member_number ?? '-' }})</span></dd>
                    <dt class="col-sm-4 text-sienna">Buku</dt><dd class="col-sm-8">{{ $returnItem->borrowingItem->book?->title ?? '(buku dihapus)' }}</dd>
                    <dt class="col-sm-4 text-sienna">Jatuh tempo</dt><dd class="col-sm-8">{{ $returnItem->due_date->translatedFormat('d F Y') }}</dd>
                    <dt class="col-sm-4 text-sienna">Tanggal kembali</dt><dd class="col-sm-8">{{ $returnTx->return_date->translatedFormat('d F Y') }}</dd>
                    <dt class="col-sm-4 text-sienna">Perhitungan</dt>
                    <dd class="col-sm-8">{{ $returnItem->days_late }} hari × {{ Format::rupiah($returnItem->fine_per_day) }}
                        @if ($returnItem->quantity > 1) × {{ $returnItem->quantity }} eksemplar @endif
                        = <strong>{{ Format::rupiah($fine->amount) }}</strong>
                        <div class="small text-muted">Tarif tersimpan saat pengembalian, tidak berubah walau tarif di profil diubah.</div></dd>
                    <dt class="col-sm-4 text-sienna">Pengembalian</dt><dd class="col-sm-8">{{ $returnTx->code }} (petugas: {{ $returnTx->processor?->name ?? '-' }})</dd>
                    @if ($fine->settled_at)
                        <dt class="col-sm-4 text-sienna">Diselesaikan</dt>
                        <dd class="col-sm-8">{{ $fine->settled_at->translatedFormat('d F Y H:i') }} oleh {{ $fine->settler?->name ?? '-' }}</dd>
                    @endif
                    @if ($fine->notes)
                        <dt class="col-sm-4 text-sienna">Catatan</dt><dd class="col-sm-8">{{ $fine->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        @if ($isUnpaid)
            <div class="card dash-card mb-3">
                <div class="card-header dash-card-header"><i class="bi bi-cash-stack me-2"></i>Catat Pembayaran</div>
                <div class="card-body">
                    <form method="POST" action="{{ Panel::route('fines.pay', $fine) }}" data-once>
                        @csrf
                        <div class="row g-3">
                            <x-form-field name="amount" label="Nominal (Rp)" type="number" :value="(int) $fine->remaining_amount"
                                          :required="true" min="1" :max="(int) $fine->remaining_amount" step="1"
                                          :help="'Sisa denda ' . Format::rupiah($fine->remaining_amount) . '. Boleh dicicil.'" />
                            <x-form-select name="method" label="Metode" :options="$methods" value="cash" :required="true" placeholder="-- Pilih metode --" />
                            <x-form-field name="notes" label="Catatan" maxlength="255" />
                        </div>
                        <button type="submit" class="btn btn-success mt-3"><i class="bi bi-check-lg me-1"></i>Konfirmasi Pembayaran</button>
                    </form>
                </div>
            </div>

            @if ($canWaive)
                <div class="card dash-card">
                    <div class="card-header dash-card-header"><i class="bi bi-gift me-2"></i>Bebaskan Denda</div>
                    <div class="card-body">
                        <p class="small text-muted">Sisa denda {{ Format::rupiah($fine->remaining_amount) }} dihapus dan dicatat sebagai pembebasan. Hanya Super Admin yang dapat melakukannya.</p>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#waiveModal">
                            <i class="bi bi-gift me-1"></i>Bebaskan Sisa Denda
                        </button>
                    </div>
                </div>
            @endif
        @else
            <div class="alert alert-secondary mb-0"><i class="bi bi-check-circle me-2"></i>Denda ini sudah <strong>{{ Str::lower($fine->status->label()) }}</strong> dan tidak dapat diubah lagi.</div>
        @endif
    </div>
</div>

<div class="card dash-card">
    <div class="card-header dash-card-header"><i class="bi bi-clock-history me-2"></i>Riwayat Pembayaran</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Waktu</th><th>Jenis</th><th class="text-end">Nominal</th><th>Metode</th><th>Petugas</th><th>Catatan</th></tr></thead>
            <tbody>
                @forelse ($fine->payments as $payment)
                    <tr>
                        <td class="text-nowrap small">{{ $payment->paid_at->translatedFormat('d M Y H:i') }}</td>
                        <td>
                            <span class="badge badge-status {{ $payment->type === 'waiver' ? 'badge-returned' : 'badge-available' }}">
                                {{ $payment->type === 'waiver' ? 'Pembebasan' : 'Pembayaran' }}
                            </span>
                        </td>
                        <td class="text-end">{{ Format::rupiah($payment->amount) }}</td>
                        <td>{{ $methods[$payment->method] ?? '-' }}</td>
                        <td>{{ $payment->receiver?->name ?? '-' }}</td>
                        <td>{{ $payment->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-note">Belum ada pembayaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($isUnpaid && $canWaive)
    <div class="modal fade" id="waiveModal" tabindex="-1" aria-labelledby="waiveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ Panel::route('fines.waive', $fine) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-dark-red" id="waiveModalLabel">Bebaskan Sisa Denda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p>Sisa denda <strong>{{ Format::rupiah($fine->remaining_amount) }}</strong> akan dibebaskan. Tindakan ini tidak dapat dibatalkan.</p>
                    <label for="reason" class="form-label fw-semibold">Alasan <span class="text-danger">*</span></label>
                    <input type="text" id="reason" name="reason" maxlength="255" required value="{{ old('reason') }}"
                           class="form-control @error('reason') is-invalid @enderror">
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Bebaskan</button>
                </div>
            </form>
        </div>
    </div>

    @if ($errors->has('reason'))
        @push('scripts')
            <script>document.addEventListener('DOMContentLoaded', function () { new bootstrap.Modal(document.getElementById('waiveModal')).show(); });</script>
        @endpush
    @endif
@endif
@endsection
