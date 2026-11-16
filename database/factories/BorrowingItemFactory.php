<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BorrowingItem>
 */
class BorrowingItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'borrowing_id' => Borrowing::factory(),
            'book_id' => Book::factory(),
            'quantity' => 1,
            'returned_quantity' => 0,
            'return_status' => BorrowingItem::RETURN_NOT_RETURNED,
        ];
    }
}
