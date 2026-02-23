<?php

declare(strict_types=1);

namespace App\Services\Chat;

/**
 * DTO for AI router response. Strict schema: intent, target_block_key, target_step_key, confidence, etc.
 */
final class RouterResult
{
    public function __construct(
        public readonly string $intent,
        public readonly string $targetBlockKey,
        public readonly ?string $targetStepKey,
        public readonly float $confidence,
        public readonly string $reason,
        public readonly string $customerMessage,
        public readonly bool $requireConfirmation,
        /** @var array<string, mixed> */
        public readonly array $variables,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'target_block_key' => $this->targetBlockKey,
            'target_step_key' => $this->targetStepKey,
            'confidence' => $this->confidence,
            'reason' => $this->reason,
            'customer_message' => $this->customerMessage,
            'require_confirmation' => $this->requireConfirmation,
            'variables' => $this->variables,
        ];
    }
}
