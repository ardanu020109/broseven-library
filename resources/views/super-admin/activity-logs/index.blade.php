@extends('layouts.super-admin')

@section('title', 'Log Aktivitas')
@section('page_title', 'Log Aktivitas')

@section('content')
<x-page-header title="Log Aktivitas" :subtitle="number_format($logs->total(), 0, ',', '.') . ' aktivitas tercatat'" />

<div class="card dash-card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('super-admin.activity-logs.index') }}" class="row g-2" role="search">
            <div class="col-lg-3">
                <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control"
                       placeholder="Cari deskripsi..." aria-label="Cari aktivitas">
            </div>
            <div class="col-6 col-lg-2">
                <select name="action" class="form-select" aria-label="Jenis aktivitas" data-autosubmit>
                    <option value="">Semua jenis</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <select name="user" class="form-select" aria-label="Pengguna" data-autosubmit>
                    <option value="">Semua pengguna</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}" @selected($filters['user'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control" aria-label="Dari tanggal">
            </div>
            <div class="col-6 col-lg-2">
                <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control" aria-label="Sampai tanggal">
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                @if ($hasFilters)
                    <a href="{{ route('super-admin.activity-logs.index') }}" class="btn btn-outline-secondary">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Keterangan</th><th>IP</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        [$label, $icon, $tone] = \App\Support\ActivityLabel::describe($log->action);
                    @endphp
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at->translatedFormat('d M Y H:i:s') }}</td>
                        <td>
                            @if ($log->user)
                                <div class="fw-semibold">{{ $log->user->name }}</div>
                                <div class="small text-muted">{{ $log->user->role->label() }}</div>
                            @else
                                <span class="text-muted">Tamu / sistem</span>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            <i class="bi {{ $icon }} {{ $tone }} me-1"></i>{{ $label }}
                            <div class="small text-muted">{{ $log->action }}</div>
                        </td>
                        <td>
                            {{ $log->description ?: '-' }}
                            @if (! empty($log->properties))
                                <details class="small text-muted">
                                    <summary>Detail</summary>
                                    <pre class="mb-0 small">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </td>
                        <td class="small text-nowrap">{{ $log->ip_address ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-note">{{ $hasFilters ? 'Tidak ada aktivitas yang cocok dengan filter.' : 'Belum ada aktivitas tercatat.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
