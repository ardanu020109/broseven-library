@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', 'Peminjaman ' . $borrowing->code)
@section('page_title', 'Peminjaman')

@section('content')
@php
    $isPending = $borrowing->status->value === 'pending';
    $member = $borrowing->member;
@endphp

<x-page-header title="Detail Peminjaman" :subtitle="$borrowing->code">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ Panel::route('borrowings.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        @if ($borrowing->status->holdsStock())
            <a href="{{ Panel::route('returns.create', $borrowing) }}" class="btn btn-primary">
                <i class="bi bi-journal-arrow-down me-1"></i>Proses Pengembalian
            </a>
        @endif
        @if (! in_array($borrowing->status->value, ['pending', 'rejected', 'cancelled'], true))
            <a href="{{ Panel::route('borrowings.receipt', $borrowing) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">
                <i class="bi bi-printer me-1"></i>Cetak Bukti
            </a>
        @endif
    </div>
</x-page-header>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-journal-text me-2"></i>Informasi Transaksi</span>
                <x-borrowing-status :borrowing="$borrowing" />
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-sienna">Kode</dt><dd class="col-sm-8">{{ $borrowing->code }}</dd>
                    <dt class="col-sm-4 text-sienna">Diajukan</dt><dd class="col-sm-8">{{ $borrowing->requested_at->translatedFormat('d F Y H:i') }}</dd>
                    <dt class="col-sm-4 text-sienna">Disetujui</dt>
                    <dd class="col-sm-8">
                        @if ($borrowing->approved_at)
                            {{ $borrowing->approved_at->translatedFormat('d F Y H:i') }} oleh {{ $borrowing->processor?->name ?? '-' }}
                        @else - @endif
                    </dd>
                    <dt class="col-sm-4 text-sienna">Tanggal pinjam</dt><dd class="col-sm-8">{{ $borrowing->borrow_date?->translatedFormat('d F Y') ?? '-' }}</dd>
                    <dt class="col-sm-4 text-sienna">Jatuh tempo</dt>
                    <dd class="col-sm-8">
                        @if ($borrowing->due_date)
                            {{ $borrowing->due_date->translatedFormat('d F Y') }}
                            @if ($borrowing->is_overdue)
                                <span class="badge badge-status badge-overdue">Terlambat {{ abs($borrowing->days_remaining) }} hari</span>
                            @elseif ($borrowing->status->holdsStock())
                                <span class="text-muted small">({{ $borrowing->days_remaining }} hari lagi)</span>
                            @endif
                        @else - @endif
                    </dd>
                    <dt class="col-sm-4 text-sienna">Catatan anggota</dt><dd class="col-sm-8">{{ $borrowing->notes ?: '-' }}</dd>
                    @if ($borrowing->rejection_reason)
                        <dt class="col-sm-4 text-sienna">Alasan penolakan</dt>
                        <dd class="col-sm-8 text-danger">{{ $borrowing->rejection_reason }}
                            @if ($borrowing->processor)<span class="text-muted">(oleh {{ $borrowing->processor->name }})</span>@endif
                        </dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-person me-2"></i>Anggota</div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="{{ $member->photo_url }}" alt="" class="user-avatar" style="width:56px;height:56px">
                    <div>
                        <a href="{{ Panel::route('members.show', $member) }}" class="fw-bold text-decoration-none">{{ $member->name }}</a>
                        <div class="small text-muted">{{ $member->memberProfile?->member_number ?? 'Tanpa nomor anggota' }}</div>
                    </div>
                </div>
                <dl class="row mb-0 small">
                    <dt class="col-4 text-sienna">Email</dt><dd class="col-8">{{ $member->email }}</dd>
                    <dt class="col-4 text-sienna">Telepon</dt><dd class="col-8">{{ $member->phone ?: '-' }}</dd>
                    <dt class="col-4 text-sienna">Akun</dt>
                    <dd class="col-8"><span class="badge badge-status {{ $member->is_active ? 'badge-available' : 'badge-overdue' }}">{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</span></dd>
                    <dt class="col-4 text-sienna">Keanggotaan</dt>
                    <dd class="col-8">
                        @if ($member->memberProfile)
                            <span class="badge badge-status {{ $member->memberProfile->is_valid ? 'badge-available' : 'badge-overdue' }}">
                                {{ $member->memberProfile->is_valid ? 'Berlaku' : 'Tidak berlaku' }}
                            </span>
                            <span class="text-muted">s.d. {{ $member->memberProfile->expired_at?->translatedFormat('d M Y') ?? '-' }}</span>
                        @else - @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card dash-card mb-4">
    <div class="card-header dash-card-header"><i class="bi bi-journal-bookmark me-2"></i>Buku yang Dipinjam</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Buku</th><th class="text-center">Jumlah</th><th class="text-center">Dikembalikan</th><th class="text-center">Stok Tersedia Saat Ini</th></tr></thead>
            <tbody>
                @foreach ($borrowing->items as $item)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{ $item->book?->cover_url ?? asset('assets/images/default-cover.svg') }}" alt="" class="thumb">
                                <div>
                                    <div class="fw-semibold">{{ $item->book?->title ?? '(buku dihapus)' }}</div>
                                    <div class="small text-muted">{{ $item->book?->isbn ?: 'Tanpa ISBN' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-center">{{ $item->returned_quantity }}</td>
                        <td class="text-center">
                            @if ($item->book)
                                <span class="badge badge-status {{ $item->book->available_stock >= $item->quantity ? 'badge-available' : 'badge-overdue' }}">{{ $item->book->available_stock }}</span>
                            @else - @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if ($borrowing->returns->isNotEmpty())
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header"><i class="bi bi-journal-arrow-down me-2"></i>Riwayat Pengembalian</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Kode</th><th>Tanggal</th><th>Buku</th><th class="text-end">Denda</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @foreach ($borrowing->returns as $return)
                        <tr>
                            <td class="fw-semibold">{{ $return->code }}</td>
                            <td class="text-nowrap small">{{ $return->return_date->translatedFormat('d M Y') }}</td>
                            <td>{{ $return->items->map(fn ($i) => $i->borrowingItem->book?->title ?? '(dihapus)')->join(', ') }}</td>
                            <td class="text-end">{{ \App\Support\Format::rupiah($return->items->sum('fine_amount')) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ Panel::route('returns.show', $return) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail"><i class="bi bi-eye"></i></a>
                                <a href="{{ Panel::route('returns.receipt', $return) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Cetak bukti" aria-label="Cetak bukti"><i class="bi bi-printer"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if ($isPending)
    <div class="card dash-card">
        <div class="card-header dash-card-header"><i class="bi bi-check2-square me-2"></i>Tindakan</div>
        <div class="card-body">
            @if (count($problems) > 0)
                <div class="alert alert-danger">
                    <strong>Pengajuan ini belum bisa disetujui:</strong>
                    <ul class="mb-0">@foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul>
                </div>
            @else
                <p class="mb-3">
                    Jika disetujui, stok buku dikurangi dan jatuh tempo ditetapkan
                    <strong>{{ $dueDatePreview->translatedFormat('d F Y') }}</strong> ({{ $durationDays }} hari dari hari ini).
                </p>
            @endif

            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ Panel::route('borrowings.approve', $borrowing) }}" data-once>
                    @csrf
                    <button type="submit" class="btn btn-success" @disabled(count($problems) > 0)>
                        <i class="bi bi-check-lg me-1"></i>Setujui dan Catat Peminjaman
                    </button>
                </form>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-lg me-1"></i>Tolak
                </button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ Panel::route('borrowings.reject', $borrowing) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-dark-red" id="rejectModalLabel">Tolak Pengajuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label for="reason" class="form-label fw-semibold">Alasan penolakan <span class="text-danger">*</span></label>
                    <textarea id="reason" name="reason" rows="3" maxlength="500" required
                              class="form-control @error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Alasan ini dapat dilihat oleh anggota.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
                </div>
            </form>
        </div>
    </div>

    @if ($errors->has('reason'))
        @push('scripts')
            <script>document.addEventListener('DOMContentLoaded', function () { new bootstrap.Modal(document.getElementById('rejectModal')).show(); });</script>
        @endpush
    @endif
@endif
@endsection
