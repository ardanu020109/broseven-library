@extends('layouts.anggota')

@section('title', 'Riwayat Peminjaman')
@section('page_title', 'Riwayat Peminjaman')

@section('content')
<x-page-header title="Riwayat Peminjaman" subtitle="Peminjaman yang sudah selesai, ditolak, atau dibatalkan.">
    <a href="{{ route('anggota.borrowings.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Peminjaman Saya</a>
</x-page-header>

<div class="card dash-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Kode</th><th>Buku</th><th>Diajukan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                @forelse ($borrowings as $borrowing)
                    <tr>
                        <td class="text-nowrap fw-semibold">{{ $borrowing->code }}</td>
                        <td>{{ $borrowing->items->map(fn ($i) => $i->book?->title ?? '(dihapus)')->join(', ') }}</td>
                        <td class="text-nowrap small">{{ $borrowing->requested_at->translatedFormat('d M Y') }}</td>
                        <td><x-borrowing-status :borrowing="$borrowing" /></td>
                        <td class="text-end">
                            <a href="{{ route('anggota.borrowings.show', $borrowing) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-note">Belum ada riwayat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($borrowings->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $borrowings->links() }}</div>
    @endif
</div>
@endsection
