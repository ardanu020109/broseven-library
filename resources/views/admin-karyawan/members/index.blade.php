@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', 'Data Anggota')
@section('page_title', 'Data Anggota')

@section('content')
<x-page-header title="Data Anggota" :subtitle="$members->total() . ' anggota ditemukan'" />

<div class="card dash-card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ Panel::route('members.index') }}" class="row g-2" role="search">
            <div class="col-lg-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control"
                           placeholder="Nama, username, email, atau nomor anggota..." aria-label="Cari anggota">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <select name="status" class="form-select" aria-label="Status akun" data-autosubmit>
                    <option value="">Semua status</option>
                    <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                @if ($hasFilters)<a href="{{ Panel::route('members.index') }}" class="btn btn-outline-secondary">Reset</a>@endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Anggota</th><th>Nomor Anggota</th><th>Masa Berlaku</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{ $member->photo_url }}" alt="" class="user-avatar">
                                <div>
                                    <a href="{{ Panel::route('members.show', $member) }}" class="fw-semibold text-decoration-none">{{ $member->name }}</a>
                                    <div class="small text-muted">{{ $member->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $member->memberProfile?->member_number ?? '-' }}</td>
                        <td class="small">{{ $member->memberProfile?->expired_at?->translatedFormat('d M Y') ?? '-' }}</td>
                        <td>
                            <span class="badge badge-status {{ $member->is_active ? 'badge-available' : 'badge-overdue' }}">{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            @if ($member->memberProfile && ! $member->memberProfile->is_valid)
                                <span class="badge badge-status badge-pending">Keanggotaan tidak berlaku</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ Panel::route('members.show', $member) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-note">{{ $hasFilters ? 'Tidak ada anggota yang cocok.' : 'Belum ada anggota.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($members->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $members->links() }}</div>
    @endif
</div>
@endsection
