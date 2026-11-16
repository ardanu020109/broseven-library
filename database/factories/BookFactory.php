<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Shelf;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->numberBetween(2, 12);

        return [
            'isbn' => fake()->unique()->isbn13(),
            'title' => Str::title(fake()->unique()->words(3, true)),
            'category_id' => Category::factory(),
            'author_id' => Author::factory(),
            'publisher_id' => Publisher::factory(),
            'shelf_id' => Shelf::factory(),
            'shelf_number' => (string) fake()->numberBetween(1, 20),
            'publication_year' => fake()->numberBetween(1995, 2025),
            'language' => 'Indonesia',
            'edition' => 'Cetakan ke-' . fake()->numberBetween(1, 5),
            'description' => fake()->paragraph(),
            'cover' => null,
            'pages' => fake()->numberBetween(100, 600),
            'price' => fake()->numberBetween(40, 250) * 1000,
            'total_stock' => $total,
            'available_stock' => $total,
            'borrowed_stock' => 0,
            'status' => Book::STATUS_ACTIVE,
        ];
    }

    /** Atur stok; stok dipinjam dihitung otomatis agar constraint tetap terpenuhi. */
    public function withStock(int $total, int $available): static
    {
        return $this->state(fn () => [
            'total_stock' => $total,
            'available_stock' => $available,
            'borrowed_stock' => $total - $available,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->withStock(3, 0);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => Book::STATUS_INACTIVE]);
    }
}
