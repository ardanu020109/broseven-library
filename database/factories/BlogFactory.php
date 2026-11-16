<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Blog>
 */
class BlogFactory extends Factory
{
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(6), '.');

        return [
            'blog_category_id' => BlogCategory::factory(),
            'author_id' => User::factory()->superAdmin(),
            'title' => $title,
            'slug' => Str::slug($title),
            'thumbnail' => null,
            'excerpt' => fake()->sentence(14),
            'content' => implode("\n\n", fake()->paragraphs(4)),
            'status' => Blog::STATUS_PUBLISHED,
            'published_at' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Blog::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }
}
