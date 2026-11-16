@extends('layouts.guest')

@section('title', 'Kontak')
@section('meta_description', 'Alamat, telepon, email, dan jam operasional ' . $libraryProfile->name)

@section('content')
<section class="page-header">
    <div class="container">
        <h1>Kontak</h1>
        <p>Hubungi {{ $libraryProfile->name }}</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="info-card h-100">
                    <i class="bi bi-geo-alt"></i>
                    <h3>Alamat</h3>
                    <p>{{ $libraryProfile->address ?: 'Belum diisi.' }}</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="info-card h-100">
                    <i class="bi bi-telephone"></i>
                    <h3>Telepon</h3>
                    @if ($libraryProfile->phone)
                        <p>{{ $libraryProfile->phone }}</p>
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $libraryProfile->phone) }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-telephone-outbound me-1"></i>Hubungi
                        </a>
                    @else
                        <p>Belum diisi.</p>
                    @endif
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="info-card h-100">
                    <i class="bi bi-envelope"></i>
                    <h3>Email</h3>
                    @if ($libraryProfile->email)
                        <p>{{ $libraryProfile->email }}</p>
                        <a href="mailto:{{ $libraryProfile->email }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-send me-1"></i>Kirim Email
                        </a>
                    @else
                        <p>Belum diisi.</p>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="info-card h-100">
                    <i class="bi bi-clock"></i>
                    <h3>Jam Operasional</h3>
                    <p>{!! $libraryProfile->operating_hours ? nl2br(e($libraryProfile->operating_hours)) : 'Belum diisi.' !!}</p>
                </div>
            </div>

            <div class="col-md-6">
                <div class="info-card h-100">
                    <i class="bi bi-share"></i>
                    <h3>Media Sosial</h3>
                    @include('guest.partials.social-links')
                    @if ($libraryProfile->website)
                        <p class="mt-3 mb-0 small">
                            <a href="{{ $libraryProfile->website }}" target="_blank" rel="noopener noreferrer">{{ $libraryProfile->website }}</a>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
