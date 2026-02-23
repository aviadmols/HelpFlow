<?php

namespace Database\Factories;

use App\Models\Block;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlockFactory extends Factory
{
    protected $model = Block::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'title' => fake()->words(3, true),
            'sort_order' => 0,
        ];
    }
}
