<?php

namespace Tests\Unit;

use App\Services\FineCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FineCalculatorTest extends TestCase
{
    private function calc(string $due, string $returned, float|int $perDay = 2000, int $qty = 1): array
    {
        return (new FineCalculator())->calculate(Carbon::parse($due), Carbon::parse($returned), $perDay, $qty);
    }

    public function test_example_from_the_specification(): void
    {
        // Jatuh tempo 10 November, kembali 13 November, Rp2.000 per hari: 3 hari, Rp6.000.
        $result = $this->calc('2026-11-10', '2026-11-13');

        $this->assertSame(3, $result['days_late']);
        $this->assertSame(6000.0, $result['fine_amount']);
        $this->assertSame(2000.0, $result['fine_per_day']);
    }

    public function test_on_time_or_early_return_has_no_fine(): void
    {
        $this->assertSame(0.0, $this->calc('2026-11-10', '2026-11-10')['fine_amount']);
        $this->assertSame(0, $this->calc('2026-11-10', '2026-11-08')['days_late']);
        $this->assertSame(0.0, $this->calc('2026-11-10', '2026-11-01')['fine_amount']);
    }

    public function test_calendar_dates_are_compared_ignoring_time_of_day(): void
    {
        // Jatuh tempo pukul 23:59 dan kembali 00:01 keesokan harinya = 1 hari terlambat.
        $this->assertSame(1, $this->calc('2026-11-10 23:59:00', '2026-11-11 00:01:00')['days_late']);

        // Jatuh tempo pukul 00:01 dan kembali pukul 23:59 di hari yang sama = tepat waktu.
        $this->assertSame(0, $this->calc('2026-11-10 00:01:00', '2026-11-10 23:59:00')['days_late']);
    }

    public function test_works_across_month_and_year_boundaries(): void
    {
        $this->assertSame(4, $this->calc('2026-12-30', '2027-01-03')['days_late']);
        $this->assertSame(2, $this->calc('2028-02-28', '2028-03-01')['days_late']); // tahun kabisat
    }

    public function test_quantity_multiplies_the_fine(): void
    {
        $this->assertSame(12000.0, $this->calc('2026-11-10', '2026-11-13', 2000, 2)['fine_amount']);
        $this->assertSame(6000.0, $this->calc('2026-11-10', '2026-11-13', 2000, 0)['fine_amount']); // minimal 1
    }

    public function test_decimal_rates_are_rounded_to_two_places(): void
    {
        $this->assertSame(3750.0, $this->calc('2026-11-10', '2026-11-13', '1250.00')['fine_amount']);
        $result = $this->calc('2026-11-10', '2026-11-13', 0.333);

        $this->assertSame(0.33, $result['fine_per_day']);
        $this->assertSame(0.99, $result['fine_amount']);
    }

    public function test_zero_rate_never_produces_a_fine(): void
    {
        $this->assertSame(0.0, $this->calc('2026-11-10', '2026-11-20', 0)['fine_amount']);
    }
}
