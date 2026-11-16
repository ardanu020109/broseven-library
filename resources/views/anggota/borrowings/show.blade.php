@extends('layouts.anggota')

@section('title', 'Peminjaman ' . $borrowing->code)
@section('page_title', 'Peminjaman Saya')

@section('content')
    <x-page-header title="Detail Peminjaman" :subtitle="$borrowing->code">
        <div class="d-flex gap-2">
            <a href="{{ route('anggota.borrowings.index') }}" class="btn btn-outline-secondary"><i
                    class="bi bi-arrow-left me-1"></i>Kembali</a>
            @if ($borrowing->status->value === 'pending')
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <i class="bi bi-x-circle me-1"></i>Batalkan Pengajuan
                </button>
            @endif
        </div>
    </x-page-header>

    @if ($borrowing->status->value === 'pending')
        <div class="alert alert-warning"><i class="bi bi-hourglass-split me-2"></i>Pengajuan Anda menunggu persetujuan
            petugas. Stok belum dikurangi.</div>
    @elseif ($borrowing->status->value === 'rejected')
        <div class="alert alert-danger"><i class="bi bi-x-octagon me-2"></i><strong>Pengajuan ditolak.</strong>
            {{ $borrowing->rejection_reason }}</div>
    @elseif ($borrowing->is_overdue)
        <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i>Peminjaman ini <strong>terlambat
                {{ abs($borrowing->days_remaining) }} hari</strong>. Segera kembalikan buku ke perpustakaan.</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-text me-2"></i>Informasi</span>
                    <x-borrowing-status :borrowing="$borrowing" />
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-sienna">Kode</dt>
                        <dd class="col-sm-7">{{ $borrowing->code }}</dd>
                        <dt class="col-sm-5 text-sienna">Diajukan</dt>
                        <dd class="col-sm-7">{{ $borrowing->requested_at->translatedFormat('d F Y H:i') }}</dd>
                        <dt class="col-sm-5 text-sienna">Disetujui</dt>
                        <dd class="col-sm-7">{{ $borrowing->approved_at?->translatedFormat('d F Y H:i') ?? '-' }}</dd>
                        <dt class="col-sm-5 text-sienna">Tanggal pinjam</dt>
                        <dd class="col-sm-7">{{ $borrowing->borrow_date?->translatedFormat('d F Y') ?? '-' }}</dd>
                        <dt class="col-sm-5 text-sienna">Jatuh tempo</dt>
                        <dd class="col-sm-7">{{ $borrowing->due_date?->translatedFormat('d F Y') ?? '-' }}</dd>
                        <dt class="col-sm-5 text-sienna">Diproses oleh</dt>
                        <dd class="col-sm-7">{{ $borrowing->processor?->name ?? '-' }}</dd>
                        <dt class="col-sm-5 text-sienna">Catatan Anda</dt>
                        <dd class="col-sm-7">{{ $borrowing->notes ?: '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header"><i class="bi bi-journal-bookmark me-2"></i>Buku</div>
                <div class="card-body p-0">
                    @foreach ($borrowing->items as $item)
                        <div class="list-row">
                            <img src="{{ $item->book?->cover_url ?? asset('assets/images/default-cover.svg') }}"
                                alt="" class="thumb">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $item->book?->title ?? '(buku dihapus)' }}</div>
                                <div class="small text-muted">{{ $item->quantity }} eksemplar</div>
                            </div>
                            @if ($item->returned_quantity >= $item->quantity)
                                <span class="badge badge-status badge-available">Sudah kembali</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if ($borrowing->returns->isNotEmpty())
        <div class="card dash-card mt-4">
            <div class="card-header dash-card-header"><i class="bi bi-journal-arrow-down me-2"></i>Riwayat
                Pengembalian</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Tanggal</th>
                            <th>Buku</th>
                            <th class="text-center">Terlambat</th>
                            <th class="text-end">Denda</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($borrowing->returns as $return)
                            <tr>
                                <td class="fw-semibold">{{ $return->code }}</td>
                                <td class="text-nowrap small">{{ $return->return_date->translatedFormat('d M Y') }}
                                </td>
                                <td>{{ $return->items->map(fn($i) => $i->borrowingItem->book?->title ?? '(dihapus)')->join(', ') }}
                                </td>
                                <td class="text-center">{{ $return->items->max('days_late') }} hari</td>
                                <td class="text-end">
                                    {{ \App\Support\Format::rupiah($return->items->sum('fine_amount')) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($borrowing->status->value === 'pending')
        <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('anggota.borrowings.cancel', $borrowing) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title text-dark-red" id="cancelModalLabel">Batalkan Pengajuan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">Batalkan pengajuan <strong>{{ $borrowing->code }}</strong>? Tindakan ini tidak
                        dapat dibatalkan.</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tidak</button>
                        <button type="submit" class="btn btn-danger">Ya, Batalkan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
