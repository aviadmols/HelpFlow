<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Block;
use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;

/**
 * Given a conversation (flow, step, context), returns the bot message and current block options.
 * Uses cache per tenant for block/flow config.
 */
final class BlockPresenter
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly int $cacheTtlSeconds
    ) {}

    /**
     * Get presentable block for conversation: bot message text and list of options (id, label, action_type).
     * When the current step has step-level suggestions (stepOptions), those are returned with option_source: 'step'.
     *
     * @return array{bot_message: string, block_key: string, options: array<int, array{id: int, label: string, action_type: string, option_source?: string}>}
     */
    public function present(Conversation $conversation, ?Block $block = null): array
    {
        $step = $conversation->currentStep;
        if ($step) {
            $step->load('stepOptions');
            if ($step->stepOptions->isNotEmpty()) {
                $context = $conversation->getContextArray();
                $botMessage = $this->renderer->render(
                    $step->bot_message_template ?? '',
                    $context
                );
                $options = [];
                foreach ($step->stepOptions as $opt) {
                    $options[] = [
                        'id' => $opt->id,
                        'label' => $opt->label,
                        'action_type' => $opt->action_type,
                        'option_source' => 'step',
                    ];
                }
                return [
                    'bot_message' => $botMessage,
                    'block_key' => $step->key,
                    'options' => $options,
                ];
            }
        }

        $block = $block ?? $this->resolveBlockForConversation($conversation);
        if (! $block->relationLoaded('options')) {
            $block->load('options');
        }
        $context = $conversation->getContextArray();
        $botMessage = $this->renderer->render(
            $block->message_template ?? $block->title,
            $context
        );
        $options = [];
        foreach ($block->options as $opt) {
            $options[] = [
                'id' => $opt->id,
                'label' => $opt->label,
                'action_type' => $opt->action_type,
            ];
        }

        return [
            'bot_message' => $botMessage,
            'block_key' => $block->key,
            'options' => $options,
        ];
    }

    /**
     * Resolve which block to show for the conversation (from step's allowed blocks or fallback).
     */
    public function resolveBlockForConversation(Conversation $conversation): Block
    {
        $step = $conversation->currentStep;
        $tenantId = $conversation->tenant_id ?? 0;
        $cacheKey = 'chat.blocks.tenant.'.$tenantId;

        $blocksById = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($tenantId) {
            return Block::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->orWhereNull('tenant_id')
                ->get()
                ->keyBy('id');
        });

        if ($step && $step->allowed_block_ids) {
            foreach ($step->allowed_block_ids as $blockId) {
                if (isset($blocksById[$blockId])) {
                    return $blocksById[$blockId];
                }
            }
            if ($step->fallback_block_id && isset($blocksById[$step->fallback_block_id])) {
                return $blocksById[$step->fallback_block_id];
            }
        }

        // Global fallback: first block with key main_menu or first block
        $mainMenu = Block::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orWhereNull('tenant_id')
            ->where('key', config('chat.fallback_block_key', 'main_menu'))
            ->first();
        if ($mainMenu) {
            return $mainMenu;
        }

        return Block::query()->orderBy('sort_order')->firstOrFail();
    }

    /**
     * Find block by key (tenant-scoped or global).
     */
    public function findBlockByKey(?int $tenantId, string $key): ?Block
    {
        return Block::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orWhereNull('tenant_id')
            ->where('key', $key)
            ->first();
    }
}
