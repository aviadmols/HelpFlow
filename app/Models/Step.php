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
        'flow_id', 'key', 'bot_message_template',
        'router_prompt', 'system_prompt',
        'allowed_block_ids', 'transition_rules', 'fallback_block_id', 'order_lookup_endpoint_id', 'ai_model_override',
    ];

    protected function casts(): array
    {
        return [
            'allowed_block_ids' => 'array',
            'transition_rules' => 'array',
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

    /** Block options that target this step (next_step_id). */
    public function optionsTargetingThis(): HasMany
    {
        return $this->hasMany(BlockOption::class, 'next_step_id');
    }
}
