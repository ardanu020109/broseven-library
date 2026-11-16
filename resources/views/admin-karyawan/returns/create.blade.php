@extends(\App\Support\Panel::layout())

@use('App\Support\Format')
@use('App\Support\Panel')

@section('title', 'Proses Pengembalian')
@section('page_title', 'Pengembalian')

@section('content')
<x-page-header title="Proses Pengembalian" :subtitle="$borrowing->code . ' · ' . $borrowing->member->name">
    <a href="{{ Panel::route('returns.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</x-page-header>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-person me-2"></i>Anggota</div>
            <div class="card-body">
                <div class="fw-bold">{{ $borrowing->member->name }}</div>
                <div class="small text-muted">{{ $borrowing->member->memberProfile?->member_number ?? '-' }} · {{ $borrowing->member->phone ?: 'tanpa telepon' }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-calendar-event me-2"></i>Jatuh Tempo</div>
            <div class="card-body">
                <div class="fw-bold">{{ $borrowing->due_date?->translatedFormat('d F Y') ?? '-' }}</div>
                @if ($borrowing->is_overdue)
                    <div class="text-danger small">Terlambat {{ abs($borrowing->days_remaining) }} hari (tanggal pengembalian: hari ini, {{ today()->translatedFormat('d F Y') }})</div>
                @else
                    <div class="text-success small">Belum jatuh tempo, tidak ada denda.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ Panel::route('returns.store', $borrowing) }}" id="returnForm" data-once>
    @csrf

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header"><i class="bi bi-journal-arrow-down me-2"></i>Buku yang Dikembalikan</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Buku</th><th class="text-center">Belum Kembali</th><th class="text-center">Hari Terlambat</th><th class="text-end">Denda / Eksemplar</th><th style="width:9rem" class="text-center">Dikembalikan</th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $item = $row['item'];
                            $calc = $row['calc'];
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="{{ $item->book?->cover_url ?? asset('assets/images/default-cover.svg') }}" alt="" class="thumb">
                                    <span class="fw-semibold">{{ $item->book?->title ?? '(buku dihapus)' }}</span>
                                </div>
                            </td>
                            <td class="text-center">{{ $item->outstanding_quantity }}</td>
                            <td class="text-center">{{ $calc['days_late'] }}</td>
                            <td class="text-end">{{ Format::rupiah($calc['fine_amount']) }}</td>
                            <td>
                                <input type="number" name="items[{{ $item->id }}]" min="0" max="{{ $item->outstanding_quantity }}"
                                       value="{{ old("items.{$item->id}", $item->outstanding_quantity) }}"
                                       class="form-control text-center return-qty" data-fine="{{ $calc['fine_amount'] }}"
                                       aria-label="Jumlah {{ $item->book?->title }} yang dikembalikan">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end fw-bold">Perkiraan total denda</td>
                        <td class="fw-bold text-center text-dark-red" id="fineTotal">{{ Format::rupiah(0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror

    <div class="card dash-card mb-4">
        <div class="card-body">
            <label for="notes" class="form-label fw-semibold">Catatan (opsional)</label>
            <input type="text" id="notes" name="notes" maxlength="255" value="{{ old('notes') }}" class="form-control"
                   placeholder="Contoh: sampul sedikit rusak">
            <div class="form-text">
                Isi jumlah 0 untuk buku yang belum dikembalikan. Denda dihitung otomatis oleh sistem saat disimpan
                berdasarkan tanggal jatuh tempo dan tarif pada Profil Perpustakaan.
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" id="returnSubmit"><i class="bi bi-check-lg me-1"></i>Konfirmasi Pengembalian</button>
        <a href="{{ Panel::route('returns.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/return-form.js') }}"></script>
@endpush
