<?php

use App\Models\Block;
use App\Models\Customer;
use App\Models\Flow;
use App\Models\Step;

test('chat start creates conversation and returns block', function () {
    $flow = Flow::factory()->create(['key' => 'default', 'active' => true]);
    $block = Block::factory()->create(['key' => 'main_menu', 'title' => 'Main']);
    $step = Step::factory()->for($flow)->create([
        'key' => 'main_menu',
        'allowed_block_ids' => [$block->id],
        'fallback_block_id' => $block->id,
    ]);

    $response = $this->postJson('/api/chat/start', [
        'email' => 'test@example.com',
        'name' => 'Test User',
        'flow_key' => 'default',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['conversation_id', 'customer_id', 'messages', 'block']);
    $response->assertJsonPath('block.block_key', 'main_menu');
});

test('chat message returns messages and block', function () {
    $customer = Customer::factory()->create();
    $flow = Flow::factory()->create(['active' => true]);
    $block = Block::factory()->create(['key' => 'main_menu', 'title' => 'Main']);
    $step = Step::factory()->for($flow)->create([
        'key' => 'main_menu',
        'allowed_block_ids' => [$block->id],
        'fallback_block_id' => $block->id,
    ]);
    $conversation = \App\Models\Conversation::create([
        'customer_id' => $customer->id,
        'flow_id' => $flow->id,
        'current_step_id' => $step->id,
        'status' => 'active',
    ]);

    $response = $this->postJson("/api/chat/{$conversation->id}/message", ['message' => 'I need help']);

    $response->assertOk();
    $response->assertJsonStructure(['messages', 'block']);
});
