@use('App\Support\Format')
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Peminjaman {{ $borrowing->code }}</title>
    <style>
        @page { margin: 22px 26px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #2b2220; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th { background: #B22234; color: #fff; padding: 4px; text-align: left; font-size: 8.5px; }
        table.data td { border-bottom: 1px solid #D9C6B2; padding: 4px; vertical-align: top; }
        .meta td { padding: 1.5px 0; vertical-align: top; }
        .center { text-align: center; }
        .box { border: 1px solid #D9C6B2; background: #F6EDE3; padding: 6px 8px; margin-top: 8px; }
        .sign { width: 100%; margin-top: 18px; text-align: center; }
        .sign td { width: 50%; padding-top: 2px; }
        .muted { color: #6f625c; font-size: 8px; }
    </style>
</head>
<body>
@php
    $member = $borrowing->member;
@endphp

@include('pdf._header', ['title' => 'BUKTI PEMINJAMAN BUKU'])

<table class="meta" style="width:100%;">
    <tr><td style="width:24%;">Kode peminjaman</td><td style="width:2%;">:</td><td style="width:74%;"><strong>{{ $borrowing->code }}</strong></td></tr>
    <tr><td>Anggota</td><td>:</td><td>{{ $member->name }} ({{ $member->memberProfile?->member_number ?? '-' }})</td></tr>
    <tr><td>Tanggal pinjam</td><td>:</td><td>{{ $borrowing->borrow_date?->translatedFormat('d F Y') ?? '-' }}</td></tr>
    <tr><td>Jatuh tempo</td><td>:</td><td><strong>{{ $borrowing->due_date?->translatedFormat('d F Y') ?? '-' }}</strong></td></tr>
    <tr><td>Petugas</td><td>:</td><td>{{ $borrowing->processor?->name ?? '-' }}</td></tr>
</table>

<table class="data">
    <thead><tr><th style="width:6%;">No</th><th>Judul Buku</th><th style="width:22%;">ISBN</th><th class="center" style="width:10%;">Jml</th></tr></thead>
    <tbody>
        @foreach ($borrowing->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->book?->title ?? '(buku dihapus)' }}</td>
                <td>{{ $item->book?->isbn ?: '-' }}</td>
                <td class="center">{{ $item->quantity }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="box">
    <strong>Ketentuan:</strong> kembalikan buku paling lambat pada tanggal jatuh tempo.
    Keterlambatan dikenakan denda {{ Format::rupiah($profile->fine_per_day) }} per hari per buku.
</div>

<table class="sign">
    <tr><td>Petugas</td><td>Peminjam</td></tr>
    <tr><td style="height:38px;"></td><td></td></tr>
    <tr><td>( {{ $borrowing->processor?->name ?? '..................' }} )</td><td>( {{ $member->name }} )</td></tr>
</table>

<p class="muted" style="margin-top:10px;">Dicetak {{ now()->translatedFormat('d F Y H:i') }} oleh {{ $printedBy }}.</p>
</body>
</html>
