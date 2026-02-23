<?php

namespace Database\Factories;

use App\Models\Flow;
use App\Models\Step;
use Illuminate\Database\Eloquent\Factories\Factory;

class StepFactory extends Factory
{
    protected $model = Step::class;

    public function definition(): array
    {
        return [
            'flow_id' => Flow::factory(),
            'key' => fake()->unique()->slug(2),
        ];
    }
}
