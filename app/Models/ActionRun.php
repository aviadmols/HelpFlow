<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record for an API action run. Stores redacted request/response, status, duration.
 */
class ActionRun extends Model
{
    protected $fillable = [
        'conversation_id', 'message_id', 'block_option_id', 'endpoint_id',
        'status', 'request_redacted', 'response_redacted',
        'http_code', 'duration_ms', 'error_message',
    ];

    /** Conversation this run belongs to. */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** Message that triggered this run (if option_click). */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** Block option that triggered this run. */
    public function blockOption(): BelongsTo
    {
        return $this->belongsTo(BlockOption::class);
    }

    /** Endpoint that was called. */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }
}
