@extends('layouts.guest')

@section('title', 'Tentang Kami')
@section('meta_description', 'Profil, aturan peminjaman, dan jam operasional ' . $libraryProfile->name)

@section('content')
<section class="page-header">
    <div class="container">
        <h1>Tentang Kami</h1>
        <p>Mengenal {{ $libraryProfile->name }}</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <h2 class="section-title">{{ $libraryProfile->name }}</h2>
                @if ($libraryProfile->tagline)
                    <p class="lead text-sienna">{{ $libraryProfile->tagline }}</p>
                @endif
                @if ($libraryProfile->description)
                    <p>{!! nl2br(e($libraryProfile->description)) !!}</p>
                @endif

                @if ($libraryProfile->head_name)
                    <p class="mt-4 mb-0 text-muted small">Kepala Perpustakaan</p>
                    <p class="fw-semibold">{{ $libraryProfile->head_name }}</p>
                @endif

                <div class="row g-3 mt-2">
                    <div class="col-4"><div class="stat-card"><div class="stat-value">{{ number_format($stats['titles'], 0, ',', '.') }}</div><div class="stat-label">Judul</div></div></div>
                    <div class="col-4"><div class="stat-card"><div class="stat-value">{{ number_format($stats['copies'], 0, ',', '.') }}</div><div class="stat-label">Eksemplar</div></div></div>
                    <div class="col-4"><div class="stat-card"><div class="stat-value">{{ number_format($stats['members'], 0, ',', '.') }}</div><div class="stat-label">Anggota</div></div></div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-body p-4">
                        <h3 class="h6 text-sienna mb-3"><i class="bi bi-clock me-2"></i>Jam Operasional</h3>
                        <p class="mb-0">{!! $libraryProfile->operating_hours ? nl2br(e($libraryProfile->operating_hours)) : '-' !!}</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-4">
                        <h3 class="h6 text-sienna mb-3"><i class="bi bi-journal-check me-2"></i>Ketentuan Peminjaman</h3>
                        <ul class="rule-list mb-3">
                            <li><i class="bi bi-check2-circle"></i>Maksimal <strong>{{ $libraryProfile->max_books_per_loan }}</strong> buku</li>
                            <li><i class="bi bi-check2-circle"></i>Durasi <strong>{{ $libraryProfile->loan_duration_days }}</strong> hari</li>
                            <li><i class="bi bi-check2-circle"></i>Denda <strong>Rp{{ number_format((float) $libraryProfile->fine_per_day, 0, ',', '.') }}</strong> per hari per buku</li>
                        </ul>
                        @if ($libraryProfile->borrowing_rules)
                            <p class="small text-muted mb-0">{!! nl2br(e($libraryProfile->borrowing_rules)) !!}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@guest
<section class="section section-beige text-center">
    <div class="container">
        <h2 class="section-title">Siap menjadi anggota?</h2>
        <p class="section-sub mb-4">Daftar gratis dan dapatkan kartu anggota digital.</p>
        <a href="{{ route('register') }}" class="btn btn-primary btn-lg">
            <i class="bi bi-person-plus me-1"></i>Daftar Anggota
        </a>
    </div>
</section>
@endguest
@endsection
