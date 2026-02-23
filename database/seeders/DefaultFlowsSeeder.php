<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Flow;
use App\Models\Step;
use App\Support\ChatConstants;
use Illuminate\Database\Seeder;

/**
 * Seeds default flow with steps: main_menu, confirm_cancel_subscription, subscription_edit_menu, handoff_offer.
 */
class DefaultFlowsSeeder extends Seeder
{
    public function run(): void
    {
        $flow = Flow::firstOrCreate(
            ['key' => 'default'],
            [
                'name' => 'Default Support Flow',
                'active' => true,
                'default_model' => 'openai/gpt-4o-mini',
                'router_prompt' => 'You are a support chat router. Given the user message, respond with ONLY valid JSON: {"intent":"...","target_block_key":"...","target_step_key":null or "step_key","confidence":0.0-1.0,"reason":"...","customer_message":"short English reply","require_confirmation":false,"variables":{}}. Allowed block keys: main_menu, subscription_management, confirmation, what_next.',
                'system_prompt' => 'You output only valid JSON. No markdown, no extra text.',
            ]
        );

        $mainMenuBlock = Block::where('key', ChatConstants::BLOCK_KEY_MAIN_MENU)->first()
            ?? Block::where('key', ChatConstants::BLOCK_KEY_SUBSCRIPTION_MANAGEMENT)->first();
        $subscriptionBlock = Block::where('key', ChatConstants::BLOCK_KEY_SUBSCRIPTION_MANAGEMENT)->first();
        $confirmationBlock = Block::where('key', ChatConstants::BLOCK_KEY_CONFIRMATION)->first();
        $whatNextBlock = Block::where('key', ChatConstants::BLOCK_KEY_WHAT_NEXT)->first();

        $mainStep = Step::firstOrCreate(
            ['flow_id' => $flow->id, 'key' => ChatConstants::STEP_KEY_MAIN_MENU],
            [
                'bot_message_template' => 'How can I help you today?',
                'allowed_block_ids' => $mainMenuBlock ? [$mainMenuBlock->id] : [],
                'fallback_block_id' => $mainMenuBlock?->id,
            ]
        );
        $confirmStep = Step::firstOrCreate(
            ['flow_id' => $flow->id, 'key' => ChatConstants::STEP_KEY_CONFIRM_CANCEL_SUBSCRIPTION],
            [
                'bot_message_template' => 'Are you sure you want to cancel your subscription?',
                'allowed_block_ids' => $confirmationBlock ? [$confirmationBlock->id] : [],
                'fallback_block_id' => $confirmationBlock?->id,
            ]
        );
        $editMenuStep = Step::firstOrCreate(
            ['flow_id' => $flow->id, 'key' => ChatConstants::STEP_KEY_SUBSCRIPTION_EDIT_MENU],
            [
                'bot_message_template' => 'You can update your subscription details here.',
                'allowed_block_ids' => $whatNextBlock ? [$whatNextBlock->id] : [],
                'fallback_block_id' => $whatNextBlock?->id,
            ]
        );
        $handoffStep = Step::firstOrCreate(
            ['flow_id' => $flow->id, 'key' => ChatConstants::STEP_KEY_HANDOFF_OFFER],
            [
                'bot_message_template' => 'Would you like to be connected to a support agent?',
                'allowed_block_ids' => $whatNextBlock ? [$whatNextBlock->id] : [],
                'fallback_block_id' => $whatNextBlock?->id,
            ]
        );

        if (! Block::where('key', ChatConstants::BLOCK_KEY_MAIN_MENU)->exists()) {
            Block::create([
                'key' => ChatConstants::BLOCK_KEY_MAIN_MENU,
                'title' => 'Main menu',
                'message_template' => 'What would you like to do?',
                'sort_order' => 0,
            ]);
        }

        $this->call(DefaultBlocksSeeder::class);
    }
}
