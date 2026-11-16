<?php

namespace Database\Factories;

use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Borrowing>
 */
class BorrowingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'PJM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
            'member_id' => User::factory(),
            'requested_at' => now(),
            'status' => BorrowingStatus::Pending->value,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => BorrowingStatus::Pending->value]);
    }

    /** Disetujui hari ini, jatuh tempo 7 hari lagi. */
    public function borrowed(): static
    {
        return $this->state(fn () => [
            'status' => BorrowingStatus::Borrowed->value,
            'approved_at' => now(),
            'borrow_date' => today()->toDateString(),
            'due_date' => today()->addDays(7)->toDateString(),
            'processed_by' => User::factory()->adminKaryawan(),
        ]);
    }

    /** Dipinjam 14 hari lalu, jatuh tempo 7 hari lalu. */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => BorrowingStatus::Overdue->value,
            'requested_at' => now()->subDays(14),
            'approved_at' => now()->subDays(14),
            'borrow_date' => today()->subDays(14)->toDateString(),
            'due_date' => today()->subDays(7)->toDateString(),
            'processed_by' => User::factory()->adminKaryawan(),
        ]);
    }
}
