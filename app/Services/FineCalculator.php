<?php

namespace App\Services;

use Carbon\CarbonInterface;

class FineCalculator
{
    /**
     * Total Denda = Jumlah Hari Terlambat x Denda Per Hari (per eksemplar).
     * Perhitungan memakai tanggal kalender: jam pada $dueDate dan $returnDate diabaikan.
     *
     * @return array{days_late: int, fine_per_day: float, fine_amount: float}
     */
    public function calculate(
        CarbonInterface $dueDate,
        CarbonInterface $returnDate,
        float|int|string $finePerDay,
        int $quantity = 1,
    ): array {
        $due = $dueDate->copy()->startOfDay();
        $returned = $returnDate->copy()->startOfDay();

        // Positif jika tanggal kembali setelah jatuh tempo; tepat waktu atau lebih awal menjadi 0.
        $daysLate = (int) max(0, $due->diffInDays($returned, false));
        $perDay = round((float) $finePerDay, 2);

        return [
            'days_late' => $daysLate,
            'fine_per_day' => $perDay,
            'fine_amount' => round($daysLate * $perDay * max(1, $quantity), 2),
        ];
    }
}
