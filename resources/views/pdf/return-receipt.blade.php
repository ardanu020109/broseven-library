@use('App\Support\Format')
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Pengembalian {{ $returnTx->code }}</title>
    <style>
        @page { margin: 22px 26px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #2b2220; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th { background: #B22234; color: #fff; padding: 4px; text-align: left; font-size: 8.5px; }
        table.data td { border-bottom: 1px solid #D9C6B2; padding: 4px; vertical-align: top; }
        .meta td { padding: 1.5px 0; vertical-align: top; }
        .right { text-align: right; } .center { text-align: center; }
        .total { background: #F6EDE3; font-weight: bold; }
        .sign { width: 100%; margin-top: 18px; text-align: center; }
        .sign td { width: 50%; padding-top: 2px; }
        .muted { color: #6f625c; font-size: 8px; }
    </style>
</head>
<body>
@php
    $borrowing = $returnTx->borrowing;
    $member = $borrowing->member;
    $totalFine = $returnTx->items->sum('fine_amount');
    $fineStatuses = $returnTx->items->map(fn ($i) => $i->fine?->status)->filter();
    $paymentSummary = match (true) {
        $totalFine <= 0 => 'Tanpa denda',
        $fineStatuses->every(fn ($s) => $s->value === 'paid') => 'Lunas',
        $fineStatuses->every(fn ($s) => $s->value === 'waived') => 'Dibebaskan',
        default => 'Belum lunas',
    };
@endphp

@include('pdf._header', ['title' => 'BUKTI PENGEMBALIAN BUKU'])

<table class="meta" style="width:100%;">
    <tr><td style="width:24%;">Kode pengembalian</td><td style="width:2%;">:</td><td style="width:74%;"><strong>{{ $returnTx->code }}</strong></td></tr>
    <tr><td>Tanggal kembali</td><td>:</td><td>{{ $returnTx->return_date->translatedFormat('d F Y') }}</td></tr>
    <tr><td>Kode peminjaman</td><td>:</td><td>{{ $borrowing->code }} (pinjam {{ $borrowing->borrow_date?->translatedFormat('d M Y') ?? '-' }}, jatuh tempo {{ $borrowing->due_date?->translatedFormat('d M Y') ?? '-' }})</td></tr>
    <tr><td>Anggota</td><td>:</td><td>{{ $member->name }} ({{ $member->memberProfile?->member_number ?? '-' }})</td></tr>
</table>

<table class="data">
    <thead>
        <tr><th style="width:5%;">No</th><th>Judul Buku</th><th class="center" style="width:9%;">Jml</th><th style="width:17%;">Jatuh Tempo</th><th class="center" style="width:12%;">Terlambat</th><th class="right" style="width:18%;">Denda</th></tr>
    </thead>
    <tbody>
        @foreach ($returnTx->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->borrowingItem->book?->title ?? '(buku dihapus)' }}</td>
                <td class="center">{{ $item->quantity }}</td>
                <td>{{ $item->due_date->translatedFormat('d M Y') }}</td>
                <td class="center">{{ $item->days_late }} hari</td>
                <td class="right">{{ Format::rupiah($item->fine_amount) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="5" class="right">Total denda</td>
            <td class="right">{{ Format::rupiah($totalFine) }}</td>
        </tr>
    </tbody>
</table>

<table class="meta" style="width:100%; margin-top:6px;">
    <tr><td style="width:24%;">Status denda</td><td style="width:2%;">:</td><td style="width:74%;"><strong>{{ $paymentSummary }}</strong></td></tr>
    @if ($returnTx->notes)
        <tr><td>Catatan</td><td>:</td><td>{{ $returnTx->notes }}</td></tr>
    @endif
    <tr><td>Tarif denda</td><td>:</td><td>{{ Format::rupiah($returnTx->items->first()?->fine_per_day ?? 0) }} per hari per buku</td></tr>
</table>

<table class="sign">
    <tr><td>Petugas</td><td>Anggota</td></tr>
    <tr><td style="height:38px;"></td><td></td></tr>
    <tr><td>( {{ $returnTx->processor?->name ?? '..................' }} )</td><td>( {{ $member->name }} )</td></tr>
</table>

<p class="muted" style="margin-top:10px;">Dicetak {{ now()->translatedFormat('d F Y H:i') }} oleh {{ $printedBy }}. Simpan bukti ini sebagai tanda terima.</p>
</body>
</html>
