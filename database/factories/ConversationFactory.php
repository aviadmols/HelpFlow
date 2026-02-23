<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Flow;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'flow_id' => Flow::factory(),
            'status' => 'active',
        ];
    }
}
