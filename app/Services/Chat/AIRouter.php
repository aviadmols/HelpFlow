<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenRouter\OpenRouterClient;
use App\Support\ChatConstants;
use Illuminate\Support\Facades\Log;

/**
 * AI router: sends user message to OpenRouter, parses strict JSON response, validates schema, stores telemetry.
 * Uses step-level prompts and fallback when set; otherwise flow/global fallback.
 */
final class AIRouter
{
    public function __construct(
        private readonly OpenRouterClient $client,
        private readonly string $fallbackBlockKey
    ) {}

    /**
     * Route free-text message to target block/step. Returns RouterResult and optionally stores AiTelemetry.
     *
     * @return array{result: RouterResult, telemetry: \App\Models\AiTelemetry|null}
     */
    public function route(Conversation $conversation, Message $customerMessage): array
    {
        $flow = $conversation->flow;
        $step = $conversation->currentStep;

        $systemPrompt = $step?->system_prompt ?? $flow->system_prompt ?? config('chat.default_system_prompt', 'You are a support chat router. Output only valid JSON.');
        $routerPrompt = $step?->router_prompt ?? $flow->router_prompt ?? config('chat.default_router_prompt', 'Given the user message, respond with JSON: intent, target_block_key, target_step_key, confidence (0-1), reason, customer_message, require_confirmation, variables (object). customer_message must NOT repeat the user.');

        $allowedBlockKeys = [];
        if ($step && ! empty($step->allowed_block_ids)) {
            $allowedBlockKeys = Block::query()
                ->whereIn('id', $step->allowed_block_ids)
                ->pluck('key')
                ->all();
        }
        $allowedStepKeys = $flow->steps()->pluck('key')->all();

        $constraints = '';
        if ($allowedBlockKeys !== []) {
            $constraints .= "\nAllowed target_block_key values (use only one of these): ".implode(', ', $allowedBlockKeys).'.';
        }
        if ($allowedStepKeys !== []) {
            $constraints .= "\nAllowed target_step_key values (optional, use only one of these or null): ".implode(', ', $allowedStepKeys).'.';
        }

        $userContent = $routerPrompt.$constraints."\n\nUser message: ".$customerMessage->content;

        $model = $step?->ai_model_override ?? $flow->default_model ?? OpenRouterClient::getDefaultModel();
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userContent],
        ];

        Log::channel('ai_router')->info('AIRouter request', ['conversation_id' => $conversation->id]);

        $response = $this->client->chat($messages, ['model' => $model]);
        $content = $response['content'] ?? null;
        $usage = $response['usage'] ?? null;

        $stepFallbackBlockKey = $step?->fallbackBlock?->key ?? $this->fallbackBlockKey;
        $result = $this->parseAndValidate($content, $stepFallbackBlockKey);
        $telemetry = $this->storeTelemetry($conversation, $customerMessage, $result, $content, $model, $usage);

        return ['result' => $result, 'telemetry' => $telemetry];
    }

    /**
     * Parse JSON content and validate schema. Returns fallback result if invalid.
     * Uses stepFallbackBlockKey when confidence is low or parse fails (step-level fallback when provided).
     */
    private function parseAndValidate(?string $content, string $stepFallbackBlockKey = null): RouterResult
    {
        $fallbackKey = $stepFallbackBlockKey ?? $this->fallbackBlockKey;
        $fallback = new RouterResult(
            intent: 'unknown',
            targetBlockKey: $fallbackKey,
            targetStepKey: null,
            confidence: 0.0,
            reason: ChatConstants::ROUTER_FALLBACK_REASON,
            customerMessage: config('chat.default_fallback_customer_message', 'Here are your options.'),
            requireConfirmation: false,
            variables: []
        );

        if ($content === null || $content === '') {
            return $fallback;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return $fallback;
        }

        $targetBlockKey = isset($decoded['target_block_key']) && is_string($decoded['target_block_key'])
            ? $decoded['target_block_key']
            : $fallbackKey;
        $confidence = isset($decoded['confidence']) && is_numeric($decoded['confidence'])
            ? (float) $decoded['confidence']
            : 0.0;

        if ($confidence < 0.5) {
            $targetBlockKey = $fallbackKey;
        }

        return new RouterResult(
            intent: is_string($decoded['intent'] ?? null) ? $decoded['intent'] : 'unknown',
            targetBlockKey: $targetBlockKey,
            targetStepKey: is_string($decoded['target_step_key'] ?? null) ? $decoded['target_step_key'] : null,
            confidence: $confidence,
            reason: is_string($decoded['reason'] ?? null) ? $decoded['reason'] : ChatConstants::ROUTER_FALLBACK_REASON,
            customerMessage: is_string($decoded['customer_message'] ?? null) && $decoded['customer_message'] !== '' ? $decoded['customer_message'] : config('chat.default_fallback_customer_message', 'Here are your options.'),
            requireConfirmation: (bool) ($decoded['require_confirmation'] ?? false),
            variables: is_array($decoded['variables'] ?? null) ? $decoded['variables'] : []
        );
    }

    /**
     * Store AI telemetry record. Redacts full response (store only keys/structure if needed).
     */
    private function storeTelemetry(
        Conversation $conversation,
        Message $message,
        RouterResult $result,
        ?string $rawContent,
        ?string $model,
        ?array $usage
    ): \App\Models\AiTelemetry {
        $redacted = $rawContent ? ['content_length' => strlen($rawContent)] : [];

        return \App\Models\AiTelemetry::create([
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'model' => $model,
            'input_tokens' => $usage['prompt_tokens'] ?? null,
            'output_tokens' => $usage['completion_tokens'] ?? null,
            'intent' => $result->intent,
            'target_block_key' => $result->targetBlockKey,
            'target_step_key' => $result->targetStepKey,
            'confidence' => $result->confidence,
            'reason' => $result->reason,
            'full_response_redacted' => $redacted,
        ]);
    }
}
