<?php

namespace Database\Factories;

use App\Models\Shelf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shelf>
 */
class ShelfFactory extends Factory
{
    public function definition(): array
    {
        $code = 'RK-' . strtoupper(fake()->unique()->bothify('?##'));

        return [
            'code' => $code,
            'name' => 'Rak ' . $code,
            'location' => 'Lantai ' . fake()->numberBetween(1, 3),
            'description' => fake()->sentence(),
        ];
    }
}
