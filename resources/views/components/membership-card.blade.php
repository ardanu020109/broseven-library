@props(['user', 'profile', 'status', 'qr', 'library'])

<div class="id-sheet">
    {{-- SISI DEPAN --}}
    <div>
        <div class="id-caption no-print">Tampilan Depan</div>
        <div class="id-card">
            <div class="id-head">
                @if ($library->logo)
                    <img src="{{ $library->logo_url }}" alt="">
                @else
                    <i class="bi bi-book-half"></i>
                @endif
                <div>
                    <div class="id-lib">{{ $library->name }}</div>
                    <div class="id-sub">KARTU ANGGOTA</div>
                </div>
            </div>

            <div class="id-body">
                <img src="{{ $user->photo_url }}" alt="Foto {{ $user->name }}" class="id-photo">
                <div class="id-info">
                    <div class="id-name">{{ $user->name }}</div>
                    <div class="id-number">{{ $profile->member_number }}</div>
                    <div class="id-row"><b>Username</b> {{ $user->username }}</div>
                    <div class="id-row"><b>Bergabung</b> {{ $profile->joined_at->translatedFormat('d M Y') }}</div>
                    <div class="id-row"><b>Berlaku s.d.</b> {{ $profile->expired_at?->translatedFormat('d M Y') ?? '-' }}</div>
                    <div class="id-status {{ $status['valid'] ? 'is-valid' : 'is-invalid' }}">{{ $status['label'] }}</div>
                </div>
            </div>
            <div class="id-foot"></div>
        </div>
    </div>

    {{-- SISI BELAKANG --}}
    <div>
        <div class="id-caption no-print">Tampilan Belakang</div>
        <div class="id-card">
            <div class="id-head id-head-slim">
                <div class="id-sub">VERIFIKASI KEANGGOTAAN</div>
            </div>

            <div class="id-body id-body-back">
                <img src="{{ $qr }}" alt="QR Code verifikasi kartu {{ $profile->member_number }}" class="id-qr">
                <div class="id-info">
                    <div class="id-row id-strong">Pindai QR untuk memverifikasi kartu ini.</div>
                    @if ($library->address)<div class="id-row">{{ $library->address }}</div>@endif
                    @if ($library->phone)<div class="id-row">Telp. {{ $library->phone }}</div>@endif
                    @if ($library->email)<div class="id-row">{{ $library->email }}</div>@endif
                    <div class="id-row id-note">Kartu ini milik anggota yang tertera. Jika menemukan kartu ini, mohon kembalikan ke perpustakaan.</div>
                </div>
            </div>
            <div class="id-foot"></div>
        </div>
    </div>
</div>
