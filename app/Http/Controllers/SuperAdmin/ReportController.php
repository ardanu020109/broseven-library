<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Exports\BorrowingExport;
use App\Exports\ReturnExport;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    private const PER_PAGE = 15;

    private const TITLES = ['borrowings' => 'Laporan Peminjaman', 'returns' => 'Laporan Pengembalian'];

    public function __construct(private readonly ReportService $reports)
    {
    }

    public function borrowings(Request $request): View
    {
        return $this->screen($request, 'borrowings');
    }

    public function returns(Request $request): View
    {
        return $this->screen($request, 'returns');
    }

    public function exportBorrowings(Request $request, string $format)
    {
        return $this->export($request, 'borrowings', $format);
    }

    public function exportReturns(Request $request, string $format)
    {
        return $this->export($request, 'returns', $format);
    }

    // ------------------------------------------------------------------

    private function screen(Request $request, string $type): View
    {
        Gate::authorize('view-reports');

        $filters = ReportFilters::fromRequest($request);

        $paginator = ($type === 'borrowings'
            ? $this->reports->borrowingQuery($filters)
            : $this->reports->returnQuery($filters)
        )->paginate(self::PER_PAGE)->withQueryString();

        $start = $paginator->firstItem() ?? 1;

        $rows = $paginator->getCollection()->values()->map(fn ($row, int $i) => $type === 'borrowings'
            ? $this->reports->formatBorrowingRow($row, $start + $i)
            : $this->reports->formatReturnRow($row, $start + $i));

        return view('super-admin.reports.index', [
            'type' => $type,
            'title' => self::TITLES[$type],
            'filters' => $filters,
            'rows' => $rows,
            'paginator' => $paginator,
            'summary' => $type === 'borrowings'
                ? $this->reports->borrowingSummary($filters)
                : $this->reports->returnSummary($filters),
            'exportQuery' => $filters->toQuery(),
        ]);
    }

    private function export(Request $request, string $type, string $format)
    {
        Gate::authorize('view-reports');

        $filters = ReportFilters::fromRequest($request);
        $base = $type === 'borrowings' ? $this->reports->borrowingBase($filters) : $this->reports->returnBase($filters);
        $total = $base->count();
        $reportRoute = route("super-admin.reports.{$type}", $filters->toQuery());

        if ($format === 'excel') {
            $max = (int) config('library.report_excel_max_rows', 20000);

            if ($total > $max) {
                return redirect($reportRoute)->with('error', sprintf(
                    'Laporan berisi %s baris, melebihi batas export Excel (%s baris). Persempit filter terlebih dahulu.',
                    number_format($total, 0, ',', '.'),
                    number_format($max, 0, ',', '.'),
                ));
            }

            $this->logExport($type, 'excel', $filters, $total);

            return Excel::download(
                $type === 'borrowings' ? new BorrowingExport($filters) : new ReturnExport($filters),
                $this->filename($type, 'xlsx'),
            );
        }

        // PDF dan cetak dibatasi ke baris pertama saja.
        $limit = (int) config('library.report_pdf_max_rows', 500);

        $rows = ($type === 'borrowings' ? $this->reports->borrowingQuery($filters) : $this->reports->returnQuery($filters))
            ->limit($limit)->get()->values()
            ->map(fn ($row, int $i) => $type === 'borrowings'
                ? $this->reports->formatBorrowingRow($row, $i + 1)
                : $this->reports->formatReturnRow($row, $i + 1));

        $data = [
            'type' => $type,
            'title' => strtoupper(self::TITLES[$type]),
            'filterLines' => $filters->summaryLines($type),
            'rows' => $rows,
            'summary' => $type === 'borrowings'
                ? $this->reports->borrowingSummary($filters)
                : $this->reports->returnSummary($filters),
            'total' => $total,
            'limit' => $limit,
            'truncated' => $total > $limit,
            'printedBy' => $request->user()->name,
        ];

        if ($format === 'print') {
            return view('super-admin.reports.print', $data + ['pageTitle' => self::TITLES[$type]]);
        }

        $this->logExport($type, 'pdf', $filters, $total);

        return Pdf::loadView('pdf.report', $data)
            ->setPaper('a4', 'landscape')
            ->download($this->filename($type, 'pdf'));
    }

    private function filename(string $type, string $extension): string
    {
        $name = $type === 'borrowings' ? 'laporan-peminjaman' : 'laporan-pengembalian';

        return $name . '-' . now()->format('Ymd-Hi') . '.' . $extension;
    }

    private function logExport(string $type, string $format, ReportFilters $filters, int $rows): void
    {
        ActivityLog::record(
            'report.exported',
            'Export ' . self::TITLES[$type] . ' (' . strtoupper($format) . ')',
            null,
            ['type' => $type, 'format' => $format, 'rows' => $rows, 'filters' => $filters->toQuery()],
        );
    }
}
