<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Single message in a conversation. Role: customer, bot, agent. Type: text, option_click, system.
 */
class Message extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'role', 'content', 'message_type', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /** Conversation this message belongs to. */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** Action runs triggered by this message (e.g. option_click). */
    public function actionRuns(): HasMany
    {
        return $this->hasMany(ActionRun::class);
    }

    /** AI telemetry for routing this message. */
    public function aiTelemetry(): HasMany
    {
        return $this->hasMany(AiTelemetry::class);
    }
}
