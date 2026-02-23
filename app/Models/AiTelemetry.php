<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI router call telemetry. Stores model, tokens, intent, target block/step, confidence.
 */
class AiTelemetry extends Model
{
    protected $table = 'ai_telemetry';

    protected $fillable = [
        'conversation_id', 'message_id', 'model',
        'input_tokens', 'output_tokens',
        'intent', 'target_block_key', 'target_step_key',
        'confidence', 'reason', 'full_response_redacted',
    ];

    protected function casts(): array
    {
        return ['full_response_redacted' => 'array'];
    }

    /** Conversation this telemetry belongs to. */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** Message that was routed. */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
