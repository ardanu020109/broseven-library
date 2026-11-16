<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Anggota {{ $profile->member_number }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: "DejaVu Sans", sans-serif; color: #2b2220; }
        .page { width: 242pt; height: 150pt; position: relative; overflow: hidden; background: #ffffff; }
        .page-break { page-break-after: always; }
        .band { background: #B22234; height: 30pt; }
        .band-slim { background: #B22234; height: 17pt; }
        .foot { position: absolute; top: 144pt; left: 0; width: 242pt; height: 6pt; background: #8B0000; }
        .lib { font-size: 8.5pt; font-weight: bold; color: #ffffff; }
        .sub { font-size: 5pt; letter-spacing: 1pt; color: #D9C6B2; }
        .name { font-size: 9.5pt; font-weight: bold; color: #8B0000; }
        .number { font-size: 8pt; color: #A0522D; letter-spacing: 0.5pt; margin-bottom: 3pt; }
        .row { font-size: 6pt; line-height: 1.5; }
        .photo-box { width: 46pt; height: 58pt; background: #D9C6B2; text-align: center; color: #A0522D; font-size: 20pt; font-weight: bold; }
        .badge { display: inline-block; margin-top: 3pt; padding: 1.5pt 6pt; font-size: 6pt; font-weight: bold; color: #ffffff; }
        .ok { background: #1e6b34; } .bad { background: #8B0000; }
        .note { font-size: 5pt; color: #6f625c; line-height: 1.4; margin-top: 3pt; }
    </style>
</head>
<body>

{{-- HALAMAN 1: DEPAN --}}
<div class="page page-break">
    <div class="band">
        <table style="width: 242pt; border-collapse: collapse;">
            <tr>
                <td style="width: 30pt; padding: 3pt 0 0 8pt; vertical-align: middle;">
                    @if ($logo)<img src="{{ $logo }}" alt="" style="height: 24pt;">@endif
                </td>
                <td style="padding: 5pt 8pt 0 {{ $logo ? '4pt' : '10pt' }}; vertical-align: middle;">
                    <div class="lib">{{ \Illuminate\Support\Str::limit($library->name, 34) }}</div>
                    <div class="sub">KARTU ANGGOTA PERPUSTAKAAN</div>
                </td>
            </tr>
        </table>
    </div>

    <table style="width: 242pt; border-collapse: collapse; margin-top: 8pt;">
        <tr>
            <td style="width: 54pt; padding-left: 8pt; vertical-align: top;">
                @if ($photo)
                    <img src="{{ $photo }}" alt="" style="width: 46pt; height: 58pt;">
                @else
                    <div class="photo-box"><div style="padding-top: 16pt;">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}</div></div>
                @endif
            </td>
            <td style="vertical-align: top; padding-right: 8pt;">
                <div class="name">{{ \Illuminate\Support\Str::limit($user->name, 26) }}</div>
                <div class="number">{{ $profile->member_number }}</div>
                <div class="row">Username: {{ $user->username }}</div>
                <div class="row">Bergabung: {{ $profile->joined_at->translatedFormat('d M Y') }}</div>
                <div class="row">Berlaku s.d.: {{ $profile->expired_at?->translatedFormat('d M Y') ?? '-' }}</div>
                <div class="badge {{ $status['valid'] ? 'ok' : 'bad' }}">{{ $status['label'] }}</div>
            </td>
        </tr>
    </table>
    <div class="foot"></div>
</div>

{{-- HALAMAN 2: BELAKANG --}}
<div class="page">
    <div class="band-slim">
        <div class="sub" style="padding: 6pt 0 0 10pt;">VERIFIKASI KEANGGOTAAN</div>
    </div>

    <table style="width: 242pt; border-collapse: collapse; margin-top: 8pt;">
        <tr>
            <td style="width: 82pt; padding-left: 8pt; vertical-align: top;">
                <img src="{{ $qr }}" alt="" style="width: 74pt; height: 74pt;">
                <div style="font-size: 5.5pt; text-align: center; width: 74pt; color: #A0522D;">{{ $profile->member_number }}</div>
            </td>
            <td style="vertical-align: top; padding-right: 8pt;">
                <div class="row" style="font-weight: bold;">Pindai QR untuk memverifikasi kartu ini.</div>
                @if ($library->address)<div class="row">{{ \Illuminate\Support\Str::limit($library->address, 70) }}</div>@endif
                @if ($library->phone)<div class="row">Telp. {{ $library->phone }}</div>@endif
                @if ($library->email)<div class="row">{{ $library->email }}</div>@endif
                <div class="note">Kartu ini milik anggota yang tertera. Jika menemukan kartu ini, mohon kembalikan ke perpustakaan.</div>
            </td>
        </tr>
    </table>
    <div class="foot"></div>
</div>

</body>
</html>
