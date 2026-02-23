<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Step in a flow. Defines bot message, allowed blocks, fallback, AI override.
 */
class Step extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id', 'key', 'sort_order', 'bot_message_template',
        'router_prompt', 'system_prompt',
        'allowed_block_ids', 'transition_rules', 'allowed_next_step_ids', 'fallback_block_id', 'order_lookup_endpoint_id', 'ai_model_override',
        'context_variables',
    ];

    protected function casts(): array
    {
        return [
            'allowed_block_ids' => 'array',
            'transition_rules' => 'array',
            'allowed_next_step_ids' => 'array',
            'context_variables' => 'array',
        ];
    }

    /** Flow this step belongs to. */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    /** Fallback block when no match. */
    public function fallbackBlock(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'fallback_block_id');
    }

    /** Optional endpoint to call after collecting email/order (e.g. fetch order details into context). */
    public function orderLookupEndpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class, 'order_lookup_endpoint_id');
    }

    /** Step-level suggestions (options) for this step. When present, these are shown instead of block options. */
    public function stepOptions(): HasMany
    {
        return $this->hasMany(StepOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Block options that target this step (next_step_id). */
    public function optionsTargetingThis(): HasMany
    {
        return $this->hasMany(BlockOption::class, 'next_step_id');
    }

    /** Steps that are allowed as next steps from this step (when allowed_next_step_ids is set). */
    public function allowedNextSteps(): \Illuminate\Database\Eloquent\Collection
    {
        $ids = $this->allowed_next_step_ids ?? [];
        if ($ids === []) {
            return $this->flow->steps()->get();
        }
        return Step::query()->whereIn('id', $ids)->orderBy('sort_order')->orderBy('id')->get();
    }
}
