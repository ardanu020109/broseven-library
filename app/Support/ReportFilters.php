<?php

namespace App\Support;

use App\Enums\BorrowingStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/** Filter laporan yang sudah dibersihkan. Nilai tidak valid diabaikan, bukan menyebabkan error. */
final class ReportFilters
{
    public const PERIODS = [
        'today' => 'Hari ini',
        'week' => 'Minggu ini',
        'month' => 'Bulan ini',
        'year' => 'Tahun ini',
        'range' => 'Rentang tanggal',
        'all' => 'Semua waktu',
    ];

    public const RETURN_STATUSES = ['on_time' => 'Tepat waktu', 'late' => 'Terlambat'];

    public const FINE_STATUSES = [
        'none' => 'Tanpa denda',
        'unpaid' => 'Belum dibayar',
        'paid' => 'Lunas',
        'waived' => 'Dibebaskan',
    ];

    private function __construct(
        public readonly string $period,
        public readonly ?Carbon $from,
        public readonly ?Carbon $to,
        public readonly string $member,
        public readonly string $book,
        public readonly string $status,
        public readonly string $returnStatus,
        public readonly string $fineStatus,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $period = $request->string('period')->toString();
        $period = array_key_exists($period, self::PERIODS) ? $period : 'month';

        [$from, $to] = match ($period) {
            'today' => [today(), today()],
            'week' => [
                now()->startOfWeek(CarbonInterface::MONDAY)->startOfDay(),
                now()->endOfWeek(CarbonInterface::SUNDAY)->startOfDay(),
            ],
            'month' => [now()->startOfMonth()->startOfDay(), now()->endOfMonth()->startOfDay()],
            'year' => [now()->startOfYear()->startOfDay(), now()->endOfYear()->startOfDay()],
            'range' => self::orderedRange(
                self::parseDate($request->string('from')->toString()),
                self::parseDate($request->string('to')->toString()),
            ),
            default => [null, null],
        };

        $status = $request->string('status')->toString();
        $returnStatus = $request->string('return_status')->toString();
        $fineStatus = $request->string('fine_status')->toString();

        return new self(
            $period,
            $from,
            $to,
            trim(Str::limit($request->string('member')->toString(), 100, '')),
            trim(Str::limit($request->string('book')->toString(), 100, '')),
            in_array($status, array_column(BorrowingStatus::cases(), 'value'), true) ? $status : '',
            array_key_exists($returnStatus, self::RETURN_STATUSES) ? $returnStatus : '',
            array_key_exists($fineStatus, self::FINE_STATUSES) ? $fineStatus : '',
        );
    }

    /** @return array<string, string> value => label */
    public static function statusOptions(): array
    {
        $options = [];

        foreach (BorrowingStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    public function label(): string
    {
        $name = self::PERIODS[$this->period];

        if ($this->from === null && $this->to === null) {
            return $this->period === 'all' ? $name : "{$name} (semua tanggal)";
        }

        $format = fn (Carbon $date) => $date->translatedFormat('d M Y');

        $range = match (true) {
            $this->from !== null && $this->to !== null => $this->from->isSameDay($this->to)
                ? $format($this->from)
                : $format($this->from) . ' s.d. ' . $format($this->to),
            $this->from !== null => 'sejak ' . $format($this->from),
            default => 'sampai ' . $format($this->to),
        };

        return "{$name} ({$range})";
    }

    /** @return array<int, string> Ringkasan filter untuk kepala PDF dan halaman cetak. */
    public function summaryLines(string $type): array
    {
        $lines = ['Periode: ' . $this->label()];

        if ($this->member !== '') {
            $lines[] = 'Anggota: ' . $this->member;
        }

        if ($this->book !== '') {
            $lines[] = 'Judul buku: ' . $this->book;
        }

        if ($type === 'borrowings' && $this->status !== '') {
            $lines[] = 'Status: ' . self::statusOptions()[$this->status];
        }

        if ($type === 'returns') {
            if ($this->returnStatus !== '') {
                $lines[] = 'Status pengembalian: ' . self::RETURN_STATUSES[$this->returnStatus];
            }

            if ($this->fineStatus !== '') {
                $lines[] = 'Status denda: ' . self::FINE_STATUSES[$this->fineStatus];
            }
        }

        return $lines;
    }

    /** @return array<string, string> Parameter query untuk tautan export dan pagination. */
    public function toQuery(): array
    {
        $query = [
            'period' => $this->period,
            'member' => $this->member,
            'book' => $this->book,
            'status' => $this->status,
            'return_status' => $this->returnStatus,
            'fine_status' => $this->fineStatus,
        ];

        if ($this->period === 'range') {
            $query['from'] = $this->from?->toDateString();
            $query['to'] = $this->to?->toDateString();
        }

        return array_filter($query, fn ($value) => $value !== null && $value !== '');
    }

    private static function parseDate(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} Tanggal yang terbalik ditukar. */
    private static function orderedRange(?Carbon $from, ?Carbon $to): array
    {
        if ($from !== null && $to !== null && $from->gt($to)) {
            return [$to, $from];
        }

        return [$from, $to];
    }
}
