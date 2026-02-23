<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Block;
use App\Models\BlockOption;
use App\Models\Endpoint;
use App\Models\Step;
use App\Support\ChatConstants;
use Illuminate\Database\Seeder;

/**
 * Seeds default blocks: Subscription Management, Confirmation, What next.
 */
class DefaultBlocksSeeder extends Seeder
{
    public function run(): void
    {
        $endpoint = Endpoint::where('key', 'recharge.create_discount')->first();
        $confirmStepId = Step::where('key', ChatConstants::STEP_KEY_CONFIRM_CANCEL_SUBSCRIPTION)->first()?->id;
        $editMenuStepId = Step::where('key', ChatConstants::STEP_KEY_SUBSCRIPTION_EDIT_MENU)->first()?->id;
        $handoffStepId = Step::where('key', ChatConstants::STEP_KEY_HANDOFF_OFFER)->first()?->id;

        $subscriptionBlock = Block::firstOrCreate(
            ['key' => ChatConstants::BLOCK_KEY_SUBSCRIPTION_MANAGEMENT],
            [
                'title' => 'Subscription Management',
                'message_template' => 'What would you like to do with your subscription?',
                'sort_order' => 10,
            ]
        );

        BlockOption::firstOrCreate(
            ['block_id' => $subscriptionBlock->id, 'label' => 'Cancel subscription'],
            ['action_type' => ChatConstants::ACTION_TYPE_CONFIRM, 'confirm_step_id' => $confirmStepId, 'sort_order' => 1]
        );
        BlockOption::firstOrCreate(
            ['block_id' => $subscriptionBlock->id, 'label' => 'Approve / update subscription details'],
            ['action_type' => ChatConstants::ACTION_TYPE_NEXT_STEP, 'next_step_id' => $editMenuStepId, 'sort_order' => 2]
        );
        BlockOption::firstOrCreate(
            ['block_id' => $subscriptionBlock->id, 'label' => 'Get 25% off next purchase (Recharge)'],
            [
                'action_type' => ChatConstants::ACTION_TYPE_API_CALL,
                'endpoint_id' => $endpoint?->id,
                'success_template' => 'Done. Your 25% discount code is: {{discount_code}}',
                'failure_template' => 'I could not create the discount right now. Do you want to talk to a support agent?',
                'next_step_on_failure_id' => $handoffStepId,
                'sort_order' => 3,
            ]
        );
        BlockOption::firstOrCreate(
            ['block_id' => $subscriptionBlock->id, 'label' => 'Talk to customer support'],
            ['action_type' => ChatConstants::ACTION_TYPE_HUMAN_HANDOFF, 'sort_order' => 4]
        );

        $confirmationBlock = Block::firstOrCreate(
            ['key' => ChatConstants::BLOCK_KEY_CONFIRMATION],
            [
                'title' => 'Confirmation',
                'message_template' => 'Please confirm.',
                'sort_order' => 20,
            ]
        );
        BlockOption::firstOrCreate(
            ['block_id' => $confirmationBlock->id, 'label' => 'Yes, confirm'],
            ['action_type' => ChatConstants::ACTION_TYPE_NEXT_STEP, 'sort_order' => 1]
        );
        BlockOption::firstOrCreate(
            ['block_id' => $confirmationBlock->id, 'label' => 'No, go back'],
            ['action_type' => ChatConstants::ACTION_TYPE_NEXT_STEP, 'sort_order' => 2]
        );

        $whatNextBlock = Block::firstOrCreate(
            ['key' => ChatConstants::BLOCK_KEY_WHAT_NEXT],
            [
                'title' => 'What would you like to do next?',
                'message_template' => 'What would you like to do next?',
                'sort_order' => 30,
            ]
        );
        BlockOption::firstOrCreate(
            ['block_id' => $whatNextBlock->id, 'label' => 'Back to main menu'],
            ['action_type' => ChatConstants::ACTION_TYPE_NEXT_STEP, 'sort_order' => 1]
        );
        BlockOption::firstOrCreate(
            ['block_id' => $whatNextBlock->id, 'label' => 'Continue here'],
            ['action_type' => ChatConstants::ACTION_TYPE_NO_OP, 'sort_order' => 2]
        );
        BlockOption::firstOrCreate(
            ['block_id' => $whatNextBlock->id, 'label' => 'Talk to support'],
            ['action_type' => ChatConstants::ACTION_TYPE_HUMAN_HANDOFF, 'sort_order' => 3]
        );
    }
}
