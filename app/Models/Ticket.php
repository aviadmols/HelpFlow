<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Human handoff ticket. Links conversation to assigned agent, status, notes.
 */
class Ticket extends Model
{
    protected $fillable = ['conversation_id', 'assigned_to', 'status', 'internal_notes'];

    /** Conversation this ticket is for. */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** Assigned agent (user). */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
