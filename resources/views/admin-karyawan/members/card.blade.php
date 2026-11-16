@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', 'Kartu ' . $member->name)
@section('page_title', 'Kartu Anggota')

@section('content')
<x-page-header title="Kartu Anggota" :subtitle="$member->name">
    <div class="d-flex flex-wrap gap-2 no-print">
        <a href="{{ Panel::route('members.show', $member) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <a href="{{ Panel::route('members.card.pdf', ['member' => $member, 'download' => 1]) }}" class="btn btn-primary">
            <i class="bi bi-download me-1"></i>Unduh PDF
        </a>
        <a href="{{ Panel::route('members.card.pdf', $member) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-pdf me-1"></i>Lihat PDF
        </a>
        <button type="button" class="btn btn-outline-secondary" data-print><i class="bi bi-printer me-1"></i>Cetak</button>
    </div>
</x-page-header>

@unless ($card['status']['valid'])
    <div class="alert alert-warning no-print">
        <i class="bi bi-exclamation-triangle me-2"></i>Status keanggotaan: <strong>{{ $card['status']['label'] }}</strong>.
    </div>
@endunless

<x-membership-card :user="$member" :profile="$profile" :status="$card['status']" :qr="$card['qr']" :library="$libraryProfile" />

<p class="small text-muted mt-4 no-print">
    Alamat verifikasi:
    <a href="{{ $card['verifyUrl'] }}" target="_blank" rel="noopener" class="text-break">{{ $card['verifyUrl'] }}</a>
</p>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-print]').forEach(function (button) {
            button.addEventListener('click', function () { window.print(); });
        });
    </script>
@endpush
