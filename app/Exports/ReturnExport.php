<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesExcelCells;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Laporan pengembalian. Dibaca bertahap (chunk) sehingga hemat memori. */
class ReturnExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;
    use SanitizesExcelCells;

    private int $number = 0;

    public function __construct(private readonly ReportFilters $filters)
    {
    }

    public function query(): Builder
    {
        return app(ReportService::class)->returnQuery($this->filters);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'No', 'Kode Pengembalian', 'Kode Peminjaman', 'Nama Anggota', 'Judul Buku',
            'Tanggal Peminjaman', 'Tanggal Jatuh Tempo', 'Tanggal Pengembalian',
            'Hari Terlambat', 'Total Denda', 'Status Pembayaran Denda', 'Admin yang Memproses',
        ];
    }

    /** @param \App\Models\ReturnItem $row */
    public function map($row): array
    {
        $cells = app(ReportService::class)->formatReturnRow($row, ++$this->number);

        return [
            $cells['no'],
            $this->safe($cells['return_code']),
            $this->safe($cells['borrowing_code']),
            $this->safe($cells['member']),
            $this->safe($cells['book']),
            $cells['borrow_date'],
            $cells['due_date'],
            $cells['return_date'],
            $cells['days_late'],
            $cells['fine'],
            $cells['fine_status'],
            $this->safe($cells['staff']),
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['J' => '#,##0'];
    }

    public function title(): string
    {
        return 'Laporan Pengembalian';
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFB22234']],
            ],
        ];
    }
}
