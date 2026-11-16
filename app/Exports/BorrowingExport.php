<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesExcelCells;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Laporan peminjaman. Dibaca bertahap (chunk) sehingga hemat memori. */
class BorrowingExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;
    use SanitizesExcelCells;

    private int $number = 0;

    public function __construct(private readonly ReportFilters $filters)
    {
    }

    public function query(): Builder
    {
        return app(ReportService::class)->borrowingQuery($this->filters);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'No', 'Kode Peminjaman', 'Nama Anggota', 'Judul Buku', 'Jumlah',
            'Tanggal Peminjaman', 'Tanggal Jatuh Tempo', 'Tanggal Pengembalian', 'Status',
        ];
    }

    /** @param \App\Models\BorrowingItem $row */
    public function map($row): array
    {
        $cells = app(ReportService::class)->formatBorrowingRow($row, ++$this->number);

        return [
            $cells['no'],
            $this->safe($cells['code']),
            $this->safe($cells['member']),
            $this->safe($cells['book']),
            $cells['quantity'],
            $cells['borrow_date'],
            $cells['due_date'],
            $cells['returned_on'],
            $cells['status'],
        ];
    }

    public function title(): string
    {
        return 'Laporan Peminjaman';
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
