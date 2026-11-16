@use('App\Support\Format')

@include('pdf._header', ['title' => $title])

<table class="meta">
    @foreach ($filterLines as $line)
        <tr><td>{{ $line }}</td></tr>
    @endforeach
</table>

@include('super-admin.reports._table', ['type' => $type, 'rows' => $rows, 'class' => 'report-table'])

@if ($truncated)
    <p class="notice">Laporan dibatasi {{ $limit }} baris pertama dari {{ $total }} baris. Gunakan Export Excel untuk data lengkap.</p>
@endif

<table class="summary">
    <tr>
        @if ($type === 'borrowings')
            <td>Total transaksi: <strong>{{ Format::number($summary['transactions']) }}</strong></td>
            <td>Total baris buku: <strong>{{ Format::number($summary['items']) }}</strong></td>
            <td>Total eksemplar: <strong>{{ Format::number($summary['books']) }}</strong></td>
        @else
            <td>Total pengembalian: <strong>{{ Format::number($summary['returns_count']) }}</strong></td>
            <td>Total buku: <strong>{{ Format::number($summary['books']) }}</strong></td>
            <td>Buku terlambat: <strong>{{ Format::number($summary['late_items']) }}</strong></td>
            <td>Total denda: <strong>{{ Format::rupiah($summary['fine_total']) }}</strong></td>
        @endif
    </tr>
</table>

<table class="footer-sign">
    <tr>
        <td class="muted">Dicetak {{ now()->translatedFormat('d F Y H:i') }} oleh {{ $printedBy }}.</td>
        <td class="sign">Petugas,<br><br><br><br>( {{ $printedBy }} )</td>
    </tr>
</table>
