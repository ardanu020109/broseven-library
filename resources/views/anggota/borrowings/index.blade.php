@extends('layouts.anggota')

@section('title', 'Peminjaman Saya')
@section('page_title', 'Peminjaman Saya')

@section('content')
<x-page-header title="Peminjaman Saya" subtitle="Pengajuan yang menunggu persetujuan dan buku yang sedang Anda pinjam.">
    <div class="d-flex gap-2">
        <a href="{{ route('anggota.borrowings.history') }}" class="btn btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>Riwayat</a>
        <a href="{{ route('anggota.catalog.index') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Pinjam Buku</a>
    </div>
</x-page-header>

<div class="card dash-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Kode</th><th>Buku</th><th>Diajukan</th><th>Jatuh Tempo</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                @forelse ($borrowings as $borrowing)
                    <tr>
                        <td class="text-nowrap fw-semibold">{{ $borrowing->code }}</td>
                        <td>{{ $borrowing->items->map(fn ($i) => $i->book?->title ?? '(dihapus)')->join(', ') }}</td>
                        <td class="text-nowrap small">{{ $borrowing->requested_at->translatedFormat('d M Y') }}</td>
                        <td class="text-nowrap small">
                            @if ($borrowing->due_date)
                                {{ $borrowing->due_date->translatedFormat('d M Y') }}
                                @if ($borrowing->is_overdue)
                                    <div class="text-danger">Lewat {{ abs($borrowing->days_remaining) }} hari</div>
                                @else
                                    <div class="text-muted">{{ $borrowing->days_remaining }} hari lagi</div>
                                @endif
                            @else - @endif
                        </td>
                        <td><x-borrowing-status :borrowing="$borrowing" /></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('anggota.borrowings.show', $borrowing) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-note">Belum ada peminjaman aktif. <a href="{{ route('anggota.catalog.index') }}">Pilih buku di katalog</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($borrowings->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $borrowings->links() }}</div>
    @endif
</div>
@endsection
