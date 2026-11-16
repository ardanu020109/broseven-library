@use('App\Support\Format')

@php
    $isBorrowings = $type === 'borrowings';
    $columnCount = $isBorrowings ? 9 : 12;
@endphp

<table class="{{ $class ?? 'report-table' }}">
    <thead>
        @if ($isBorrowings)
            <tr>
                <th>No</th><th>Kode Peminjaman</th><th>Nama Anggota</th><th>Judul Buku</th><th class="text-center">Jumlah</th>
                <th>Tanggal Peminjaman</th><th>Tanggal Jatuh Tempo</th><th>Tanggal Pengembalian</th><th>Status</th>
            </tr>
        @else
            <tr>
                <th>No</th><th>Kode Pengembalian</th><th>Kode Peminjaman</th><th>Nama Anggota</th><th>Judul Buku</th>
                <th>Tanggal Peminjaman</th><th>Tanggal Jatuh Tempo</th><th>Tanggal Pengembalian</th>
                <th class="text-center">Hari Terlambat</th><th class="text-end">Total Denda</th><th>Status Pembayaran Denda</th><th>Admin yang Memproses</th>
            </tr>
        @endif
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @if ($isBorrowings)
                <tr>
                    <td>{{ $row['no'] }}</td>
                    <td class="nowrap">{{ $row['code'] }}</td>
                    <td>{{ $row['member'] }}</td>
                    <td>{{ $row['book'] }}</td>
                    <td class="text-center">{{ $row['quantity'] }}</td>
                    <td class="nowrap">{{ $row['borrow_date'] }}</td>
                    <td class="nowrap">{{ $row['due_date'] }}</td>
                    <td class="nowrap">{{ $row['returned_on'] }}</td>
                    <td>{{ $row['status'] }}</td>
                </tr>
            @else
                <tr>
                    <td>{{ $row['no'] }}</td>
                    <td class="nowrap">{{ $row['return_code'] }}</td>
                    <td class="nowrap">{{ $row['borrowing_code'] }}</td>
                    <td>{{ $row['member'] }}</td>
                    <td>{{ $row['book'] }}</td>
                    <td class="nowrap">{{ $row['borrow_date'] }}</td>
                    <td class="nowrap">{{ $row['due_date'] }}</td>
                    <td class="nowrap">{{ $row['return_date'] }}</td>
                    <td class="text-center">{{ $row['days_late'] }}</td>
                    <td class="text-end nowrap">{{ Format::rupiah($row['fine']) }}</td>
                    <td>{{ $row['fine_status'] }}</td>
                    <td>{{ $row['staff'] }}</td>
                </tr>
            @endif
        @empty
            <tr><td colspan="{{ $columnCount }}" class="text-center">Tidak ada data pada filter yang dipilih.</td></tr>
        @endforelse
    </tbody>
</table>
