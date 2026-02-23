<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Allow admins/agents to view any conversation.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Allow admins/agents to view a conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return true;
    }
}
