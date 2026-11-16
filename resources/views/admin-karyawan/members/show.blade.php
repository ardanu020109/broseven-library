@extends(\App\Support\Panel::layout())

@use('App\Support\Format')
@use('App\Support\Panel')

@section('title', $member->name)
@section('page_title', 'Data Anggota')

@section('content')
<x-page-header title="Detail Anggota" :subtitle="$member->name">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ Panel::route('members.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        @if ($member->memberProfile)
            <a href="{{ Panel::route('members.card', $member) }}" class="btn btn-primary"><i class="bi bi-person-vcard me-1"></i>Kartu Anggota</a>
        @endif
    </div>
</x-page-header>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-person me-2"></i>Informasi</div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="{{ $member->photo_url }}" alt="" class="user-avatar" style="width:64px;height:64px">
                    <div>
                        <div class="fw-bold fs-5">{{ $member->name }}</div>
                        <span class="badge badge-status {{ $member->is_active ? 'badge-available' : 'badge-overdue' }}">{{ $member->is_active ? 'Akun aktif' : 'Akun nonaktif' }}</span>
                    </div>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-sienna">Nomor anggota</dt><dd class="col-sm-8">{{ $member->memberProfile?->member_number ?? '-' }}</dd>
                    <dt class="col-sm-4 text-sienna">Username</dt><dd class="col-sm-8">{{ $member->username }}</dd>
                    <dt class="col-sm-4 text-sienna">Email</dt><dd class="col-sm-8">{{ $member->email }}</dd>
                    <dt class="col-sm-4 text-sienna">Telepon</dt><dd class="col-sm-8">{{ $member->phone ?: '-' }}</dd>
                    <dt class="col-sm-4 text-sienna">Alamat</dt><dd class="col-sm-8">{!! $member->address ? nl2br(e($member->address)) : '-' !!}</dd>
                    <dt class="col-sm-4 text-sienna">Bergabung</dt><dd class="col-sm-8">{{ $member->memberProfile?->joined_at?->translatedFormat('d F Y') ?? '-' }}</dd>
                    <dt class="col-sm-4 text-sienna">Berlaku s.d.</dt><dd class="col-sm-8">{{ $member->memberProfile?->expired_at?->translatedFormat('d F Y') ?? '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="row g-3">
            <div class="col-6"><x-stat-card icon="bi-clock-history" label="Total Peminjaman" :value="Format::number($stats['total'])" /></div>
            <div class="col-6"><x-stat-card icon="bi-book-half" label="Belum Selesai" :value="Format::number($stats['open'])" tone="sienna" /></div>
            <div class="col-6"><x-stat-card icon="bi-exclamation-triangle" label="Terlambat" :value="Format::number($stats['overdue'])" tone="danger" /></div>
            <div class="col-6"><x-stat-card icon="bi-cash-coin" label="Denda Belum Dibayar" :value="Format::rupiah($stats['fines_unpaid'])" tone="beige" /></div>
        </div>
    </div>
</div>

<div class="card dash-card">
    <div class="card-header dash-card-header"><i class="bi bi-clock-history me-2"></i>Peminjaman Terakhir</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Kode</th><th>Buku</th><th>Diajukan</th><th>Jatuh Tempo</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($borrowings as $borrowing)
                    <tr>
                        <td class="text-nowrap">{{ $borrowing->code }}</td>
                        <td>{{ $borrowing->items->map(fn ($i) => $i->book?->title ?? '(dihapus)')->join(', ') }}</td>
                        <td class="text-nowrap small">{{ $borrowing->requested_at->translatedFormat('d M Y') }}</td>
                        <td class="text-nowrap small">{{ $borrowing->due_date?->translatedFormat('d M Y') ?? '-' }}</td>
                        <td><x-borrowing-status :borrowing="$borrowing" /></td>
                        <td class="text-end"><a href="{{ Panel::route('borrowings.show', $borrowing) }}" class="btn btn-sm btn-outline-secondary" aria-label="Detail"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-note">Anggota ini belum pernah meminjam.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
