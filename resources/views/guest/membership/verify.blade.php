@extends('layouts.guest')

@section('title', 'Verifikasi Kartu Anggota')

@section('content')
<section class="section">
    <div class="container" style="max-width: 560px">
        @if ($profile)
            <div class="card text-center">
                <div class="card-body p-4">
                    @if ($status['valid'])
                        <i class="bi bi-patch-check-fill text-success" style="font-size:3.5rem"></i>
                        <h1 class="h4 mt-2">Kartu Anggota Berlaku</h1>
                    @else
                        <i class="bi bi-x-octagon-fill text-danger" style="font-size:3.5rem"></i>
                        <h1 class="h4 mt-2">Kartu Tidak Berlaku</h1>
                        <p class="text-danger fw-semibold mb-0">{{ $status['label'] }}</p>
                    @endif

                    <hr>

                    <dl class="row text-start mb-0">
                        <dt class="col-5 text-sienna">Nama</dt><dd class="col-7">{{ $owner->name }}</dd>
                        <dt class="col-5 text-sienna">Nomor anggota</dt><dd class="col-7">{{ $profile->member_number }}</dd>
                        <dt class="col-5 text-sienna">Status</dt>
                        <dd class="col-7">
                            <span class="badge badge-status {{ $status['valid'] ? 'badge-available' : 'badge-overdue' }}">{{ $status['label'] }}</span>
                        </dd>
                        <dt class="col-5 text-sienna">Berlaku s.d.</dt><dd class="col-7">{{ $profile->expired_at?->translatedFormat('d F Y') ?? '-' }}</dd>
                    </dl>

                    <p class="small text-muted mt-3 mb-0">Diverifikasi {{ now()->translatedFormat('d F Y H:i') }} oleh {{ $libraryProfile->name }}.</p>
                </div>
            </div>
        @else
            <div class="card text-center">
                <div class="card-body p-4">
                    <i class="bi bi-question-octagon-fill text-warning" style="font-size:3.5rem"></i>
                    <h1 class="h4 mt-2">Kartu Tidak Dikenali</h1>
                    <p class="text-muted mb-0">
                        Kode pada kartu ini tidak ditemukan atau sudah diganti. Hubungi {{ $libraryProfile->name }} untuk bantuan.
                    </p>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection
