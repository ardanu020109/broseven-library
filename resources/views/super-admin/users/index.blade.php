@extends('layouts.super-admin')

@section('title', 'Data Pengguna')
@section('page_title', 'Pengguna')

@section('content')
@php
    $roleBadge = ['super-admin' => 'bg-dark-red', 'admin-master' => 'bg-crimson', 'admin-karyawan' => 'bg-sienna', 'anggota' => 'bg-beige'];
@endphp

<x-page-header title="Data Pengguna" :subtitle="$users->total() . ' pengguna ditemukan'">
    <a href="{{ route('super-admin.users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Pengguna</a>
</x-page-header>

<div class="card dash-card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('super-admin.users.index') }}" class="row g-2" role="search">
            <div class="col-lg-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control"
                           placeholder="Cari nama, username, atau email..." aria-label="Cari pengguna">
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <select name="role" class="form-select" aria-label="Role" data-autosubmit>
                    <option value="">Semua role</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <select name="status" class="form-select" aria-label="Status" data-autosubmit>
                    <option value="">Semua status</option>
                    <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                @if ($hasFilters)
                    <a href="{{ route('super-admin.users.index') }}" class="btn btn-outline-secondary">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th style="width:3.5rem">No</th><th>Pengguna</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th class="text-end" style="width:10rem">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $item)
                    <tr>
                        <td>{{ $users->firstItem() + $loop->index }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{ $item->photo_url }}" alt="" class="user-avatar">
                                <div>
                                    <a href="{{ route('super-admin.users.show', $item) }}" class="fw-semibold text-decoration-none">{{ $item->name }}</a>
                                    <div class="small text-muted">{{ '@' . $item->username }} · {{ $item->email }}</div>
                                    @if ($item->memberProfile)
                                        <div class="small text-sienna">{{ $item->memberProfile->member_number }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td><span class="badge {{ $roleBadge[$item->role->value] }}">{{ $item->role->label() }}</span></td>
                        <td>
                            <span class="badge badge-status {{ $item->is_active ? 'badge-available' : 'badge-overdue' }}">
                                {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="text-nowrap small">{{ $item->last_login_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah' }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('super-admin.users.show', $item) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('super-admin.users.edit', $item) }}" class="btn btn-sm btn-outline-primary" title="Ubah" aria-label="Ubah"><i class="bi bi-pencil"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" aria-label="Hapus"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-delete-url="{{ route('super-admin.users.destroy', $item) }}"
                                    data-delete-name="{{ $item->name }} ({{ '@' . $item->username }})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-note">{{ $hasFilters ? 'Tidak ada pengguna yang cocok dengan filter.' : 'Belum ada pengguna.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($users->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $users->links() }}</div>
    @endif
</div>

<x-delete-modal />
@endsection
