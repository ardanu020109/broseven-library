@extends('layouts.super-admin')

@use('App\Support\Format')

@section('title', $title)
@section('page_title', $title)

@section('content')
@php
    $isBorrowings = $type === 'borrowings';
    $screenRoute = route('super-admin.reports.' . $type);
    $exportRoute = fn (string $format) => route('super-admin.reports.' . $type . '.export', ['format' => $format] + $exportQuery);
@endphp

<x-page-header :title="$title" :subtitle="$filters->label()">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ $exportRoute('excel') }}" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
        <a href="{{ $exportRoute('pdf') }}" class="btn btn-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
        <a href="{{ $exportRoute('print') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Cetak</a>
    </div>
</x-page-header>

<div class="card dash-card mb-4">
    <div class="card-body">
        @include('super-admin.reports._filters', ['type' => $type, 'filters' => $filters, 'action' => $screenRoute])
    </div>
</div>

<div class="row g-3 mb-4">
    @if ($isBorrowings)
        <div class="col-md-4"><x-stat-card icon="bi-journal-text" label="Total Transaksi" :value="Format::number($summary['transactions'])" /></div>
        <div class="col-md-4"><x-stat-card icon="bi-list-ul" label="Baris Buku" :value="Format::number($summary['items'])" tone="sienna" hint="satu baris per judul" /></div>
        <div class="col-md-4"><x-stat-card icon="bi-collection" label="Total Eksemplar" :value="Format::number($summary['books'])" tone="dark" /></div>
    @else
        <div class="col-6 col-xl-3"><x-stat-card icon="bi-journal-arrow-down" label="Total Pengembalian" :value="Format::number($summary['returns_count'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card icon="bi-collection" label="Total Buku" :value="Format::number($summary['books'])" tone="sienna" /></div>
        <div class="col-6 col-xl-3"><x-stat-card icon="bi-exclamation-triangle" label="Buku Terlambat" :value="Format::number($summary['late_items'])" tone="danger" /></div>
        <div class="col-6 col-xl-3"><x-stat-card icon="bi-cash-coin" label="Total Denda" :value="Format::rupiah($summary['fine_total'])" tone="beige" /></div>
    @endif
</div>

<div class="card dash-card">
    <div class="table-responsive">
        @include('super-admin.reports._table', ['type' => $type, 'rows' => $rows, 'class' => 'table table-hover table-sm align-middle mb-0'])
    </div>

    @if ($paginator->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $paginator->links() }}</div>
    @endif
</div>

<p class="small text-muted mt-3">
    Menampilkan {{ $paginator->count() }} dari {{ number_format($paginator->total(), 0, ',', '.') }} baris.
    Export PDF dan Cetak dibatasi {{ number_format((int) config('library.report_pdf_max_rows'), 0, ',', '.') }} baris pertama, gunakan Export Excel untuk data lengkap.
</p>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/reports.js') }}"></script>
@endpush
