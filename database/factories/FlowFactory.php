<?php

namespace Database\Factories;

use App\Models\Flow;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlowFactory extends Factory
{
    protected $model = Flow::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'active' => true,
        ];
    }
}
