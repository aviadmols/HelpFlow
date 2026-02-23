<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Jobs\RunApiActionJob;
use App\Models\BlockOption;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenRouter\OpenRouterClient;
use App\Support\ChatConstants;
use Illuminate\Support\Facades\Log;

/**
 * Core engine: processes customer input (free text or option click), updates conversation state, returns messages + block.
 */
final class ConversationOrchestrator
{
    public function __construct(
        private readonly BlockPresenter $blockPresenter,
        private readonly AIRouter $router,
        private readonly TemplateRenderer $renderer
    ) {}

    /**
     * Process one customer input. Returns structured response: new_messages, block, action_status.
     *
     * @param  array{input_type: string, message?: string, option_id?: int}  $input
     * @return array{messages: array<int, array{id: int, role: string, content: string}>, block: array, action_status: ?array}
     */
    public function process(Conversation $conversation, array $input): array
    {
        $inputType = $input['input_type'] ?? ChatConstants::INPUT_TYPE_TEXT;
        Log::channel('chat')->info('Orchestrator process', ['conversation_id' => $conversation->id, 'input_type' => $inputType]);

        if ($inputType === ChatConstants::INPUT_TYPE_OPTION_CLICK && isset($input['option_id'])) {
            return $this->processOptionClick($conversation, (int) $input['option_id']);
        }

        return $this->processFreeText($conversation, (string) ($input['message'] ?? ''));
    }

    /**
     * Process free-text message: run AI router, transition step, present block, store telemetry.
     *
     * @return array{messages: array, block: array, action_status: ?array}
     */
    private function processFreeText(Conversation $conversation, string $messageText): array
    {
        $customerMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role' => ChatConstants::MESSAGE_ROLE_CUSTOMER,
            'content' => $messageText,
            'message_type' => ChatConstants::MESSAGE_TYPE_TEXT,
        ]);

        $route = $this->router->route($conversation, $customerMessage);
        $result = $route['result'];

        $this->applyRouterResultToConversation($conversation, $result);
        $conversation->save();

        $block = $this->blockPresenter->findBlockByKey($conversation->tenant_id, $result->targetBlockKey);
        $step = $conversation->currentStep;
        if ($block && $step && is_array($step->allowed_block_ids) && $step->allowed_block_ids !== [] && ! in_array($block->id, $step->allowed_block_ids, true)) {
            $block = $this->blockPresenter->resolveBlockForConversation($conversation);
        }
        if (! $block) {
            $block = $this->blockPresenter->resolveBlockForConversation($conversation);
        }
        if ($block) {
            $botContent = $result->customerMessage;
            Message::create([
                'conversation_id' => $conversation->id,
                'role' => ChatConstants::MESSAGE_ROLE_BOT,
                'content' => $botContent,
                'message_type' => ChatConstants::MESSAGE_TYPE_TEXT,
            ]);
            $conversation->update(['last_presented_block_key' => $block->key]);
            $presented = $this->blockPresenter->present($conversation, $block);
        } else {
            $presented = $this->blockPresenter->present($conversation);
        }

        $messages = $this->formatMessagesForResponse($conversation, 2);

        return [
            'messages' => $messages,
            'block' => $presented,
            'action_status' => null,
        ];
    }

    /**
     * Apply router result: set current_step_id if target_step_key given; merge variables into context.
     */
    private function applyRouterResultToConversation(Conversation $conversation, RouterResult $result): void
    {
        if ($result->targetStepKey) {
            $step = $conversation->flow->steps()->where('key', $result->targetStepKey)->first();
            if ($step) {
                $conversation->current_step_id = $step->id;
            }
        }
        if ($result->variables !== []) {
            $ctx = $conversation->getContextArray();
            foreach ($result->variables as $k => $v) {
                $ctx[$k] = $v;
            }
            $conversation->setContextArray($ctx);
        }
    }

    /**
     * Process option click: resolve option, run action (API_CALL -> job; NEXT_STEP/CONFIRM/HUMAN_HANDOFF inline).
     *
     * @return array{messages: array, block: array, action_status: ?array}
     */
    private function processOptionClick(Conversation $conversation, int $optionId): array
    {
        $option = BlockOption::with(['block', 'endpoint', 'nextStep', 'nextStepOnFailure', 'confirmStep'])
            ->find($optionId);
        if (! $option) {
            return $this->fallbackResponse($conversation, 'Invalid option.');
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => ChatConstants::MESSAGE_ROLE_CUSTOMER,
            'content' => $option->label,
            'message_type' => ChatConstants::MESSAGE_TYPE_OPTION_CLICK,
            'meta' => ['block_option_id' => $option->id, 'block_key' => $option->block->key ?? null],
        ]);

        if ($option->action_type === ChatConstants::ACTION_TYPE_API_CALL) {
            $messageId = $conversation->messages()->latest('id')->first()?->id;
            RunApiActionJob::dispatch($conversation->id, $option->id, $messageId);
            $presented = $this->blockPresenter->present($conversation);
            $messages = $this->formatMessagesForResponse($conversation, 1);

            return [
                'messages' => $messages,
                'block' => $presented,
                'action_status' => ['status' => ChatConstants::RUN_STATUS_QUEUED, 'message' => 'Action queued.'],
            ];
        }

        if ($option->action_type === ChatConstants::ACTION_TYPE_HUMAN_HANDOFF) {
            $conversation->update(['status' => ChatConstants::CONVERSATION_STATUS_HANDOFF]);
            \App\Models\Ticket::create([
                'conversation_id' => $conversation->id,
                'status' => ChatConstants::TICKET_STATUS_OPEN,
            ]);
            $botContent = 'A support agent will be with you shortly. Thank you for your patience.';
            Message::create([
                'conversation_id' => $conversation->id,
                'role' => ChatConstants::MESSAGE_ROLE_BOT,
                'content' => $botContent,
                'message_type' => ChatConstants::MESSAGE_TYPE_TEXT,
            ]);
            $presented = $this->blockPresenter->present($conversation);
            $messages = $this->formatMessagesForResponse($conversation, 2);

            return [
                'messages' => $messages,
                'block' => $presented,
                'action_status' => null,
            ];
        }

        if ($option->action_type === ChatConstants::ACTION_TYPE_RUN_PROMPT) {
            return $this->processRunPrompt($conversation, $option);
        }

        if ($option->action_type === ChatConstants::ACTION_TYPE_NEXT_STEP || $option->action_type === ChatConstants::ACTION_TYPE_CONFIRM) {
            $nextStep = $option->nextStep ?? $option->confirmStep;
            if ($nextStep) {
                $conversation->update(['current_step_id' => $nextStep->id]);
            }
            $block = $this->blockPresenter->resolveBlockForConversation($conversation);
            $step = $conversation->fresh()->currentStep;
            $template = $step?->bot_message_template ?? $block->message_template ?? $block->title ?? 'What would you like to do?';
            $botMessage = $this->renderer->render($template, $conversation->getContextArray());
            Message::create([
                'conversation_id' => $conversation->id,
                'role' => ChatConstants::MESSAGE_ROLE_BOT,
                'content' => $botMessage,
                'message_type' => ChatConstants::MESSAGE_TYPE_TEXT,
            ]);
            $conversation->update(['last_presented_block_key' => $block->key]);
            $presented = $this->blockPresenter->present($conversation, $block);
            $messages = $this->formatMessagesForResponse($conversation, 2);

            return [
                'messages' => $messages,
                'block' => $presented,
                'action_status' => null,
            ];
        }

        // NO_OP or OPEN_URL: just show same or next block
        $presented = $this->blockPresenter->present($conversation);
        $messages = $this->formatMessagesForResponse($conversation, 1);

        return [
            'messages' => $messages,
            'block' => $presented,
            'action_status' => null,
        ];
    }

    /**
     * Process RUN_PROMPT: render prompt template, call OpenRouter (ChatGPT), show response, optionally go to next step.
     *
     * @return array{messages: array, block: array, action_status: ?array}
     */
    private function processRunPrompt(Conversation $conversation, BlockOption $option): array
    {
        $promptTemplate = $option->prompt_template ?? '';
        if ($promptTemplate === '') {
            return $this->fallbackResponse($conversation, 'This action is not configured. Please set an AI prompt.');
        }

        $context = $conversation->getContextArray();
        $userPrompt = $this->renderer->render($promptTemplate, $context);

        $client = OpenRouterClient::fromConfig();
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful support assistant. Reply in a short, clear way in English. No markdown.'],
            ['role' => 'user', 'content' => $userPrompt],
        ];
        $response = $client->chat($messages);
        $content = trim((string) ($response['content'] ?? ''));
        if ($content === '') {
            $content = 'I could not generate a response right now. Please try again or choose another option.';
        }

        $context['last_prompt_response'] = $content;
        $conversation->setContextArray($context);
        $conversation->save();

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => ChatConstants::MESSAGE_ROLE_BOT,
            'content' => $content,
            'message_type' => ChatConstants::MESSAGE_TYPE_TEXT,
        ]);

        if ($option->next_step_id) {
            $conversation->update(['current_step_id' => $option->next_step_id]);
        }

        $presented = $this->blockPresenter->present($conversation->fresh());
        $messagesOut = $this->formatMessagesForResponse($conversation, 2);

        return [
            'messages' => $messagesOut,
            'block' => $presented,
            'action_status' => null,
        ];
    }

    /**
     * Format last N messages for API response.
     *
     * @return array<int, array{id: int, role: string, content: string}>
     */
    private function formatMessagesForResponse(Conversation $conversation, int $lastN = 5): array
    {
        $messages = $conversation->messages()->latest('id')->limit($lastN)->get()->reverse();

        return $messages->map(fn (Message $m) => [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content ?? '',
        ])->values()->all();
    }

    /**
     * Fallback when option invalid or error.
     *
     * @return array{messages: array, block: array, action_status: ?array}
     */
    private function fallbackResponse(Conversation $conversation, string $botContent): array
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => ChatConstants::MESSAGE_ROLE_BOT,
            'content' => $botContent,
            'message_type' => ChatConstants::MESSAGE_TYPE_TEXT,
        ]);
        $presented = $this->blockPresenter->present($conversation);
        $messages = $this->formatMessagesForResponse($conversation, 1);

        return [
            'messages' => $messages,
            'block' => $presented,
            'action_status' => null,
        ];
    }
}
