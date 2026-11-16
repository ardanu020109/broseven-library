@extends('layouts.anggota')

@section('title', 'Kartu Anggota')
@section('page_title', 'Kartu Anggota')

@section('content')
<x-page-header title="Kartu Anggota" subtitle="Kartu digital Anda. Gunakan untuk meminjam dan mengembalikan buku di perpustakaan.">
    @if ($profile)
        <div class="d-flex flex-wrap gap-2 no-print">
            <a href="{{ route('anggota.membership-card.pdf', ['download' => 1]) }}" class="btn btn-primary">
                <i class="bi bi-download me-1"></i>Unduh PDF
            </a>
            <a href="{{ route('anggota.membership-card.pdf') }}" target="_blank" rel="noopener" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-pdf me-1"></i>Lihat PDF
            </a>
            <button type="button" class="btn btn-outline-secondary" data-print>
                <i class="bi bi-printer me-1"></i>Cetak
            </button>
        </div>
    @endif
</x-page-header>

@if (! $profile)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>Anda belum memiliki data keanggotaan. Hubungi petugas perpustakaan.
    </div>
@else
    @unless ($card['status']['valid'])
        <div class="alert alert-danger no-print">
            <i class="bi bi-exclamation-octagon me-2"></i>
            Status keanggotaan Anda: <strong>{{ $card['status']['label'] }}</strong>. Kartu ini belum dapat dipakai untuk meminjam buku.
            Hubungi petugas perpustakaan.
        </div>
    @endunless

    <x-membership-card :user="$user" :profile="$profile" :status="$card['status']" :qr="$card['qr']" :library="$libraryProfile" />

    <div class="card dash-card mt-4 no-print">
        <div class="card-body">
            <h2 class="h6 text-sienna"><i class="bi bi-shield-check me-2"></i>Keamanan Kartu</h2>
            <p class="small mb-2">
                QR Code hanya berisi tautan dengan kode acak, bukan data pribadi Anda:
                <a href="{{ $card['verifyUrl'] }}" target="_blank" rel="noopener" class="text-break">{{ $card['verifyUrl'] }}</a>
            </p>
            <p class="small text-muted mb-3">
                Jika kartu hilang atau Anda merasa kodenya diketahui orang lain, perbarui kode QR.
                Kartu lama langsung tidak dapat diverifikasi.
            </p>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#regenerateModal">
                <i class="bi bi-arrow-repeat me-1"></i>Perbarui Kode QR
            </button>
        </div>
    </div>

    <div class="modal fade" id="regenerateModal" tabindex="-1" aria-labelledby="regenerateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('anggota.membership-card.regenerate') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-dark-red" id="regenerateModalLabel">Perbarui Kode QR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    Kode QR lama akan <strong>tidak berlaku</strong>. Kartu yang sudah dicetak harus dicetak ulang.
                    Lanjutkan?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Perbarui</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-print]').forEach(function (button) {
            button.addEventListener('click', function () { window.print(); });
        });
    </script>
@endpush
