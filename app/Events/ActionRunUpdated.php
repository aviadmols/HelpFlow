<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ActionRun;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an action run status changes. Broadcasts to chat.{conversationId}.
 */
class ActionRunUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ActionRun $actionRun
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('chat.'.$this->actionRun->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'action_run.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->actionRun->id,
            'conversation_id' => $this->actionRun->conversation_id,
            'status' => $this->actionRun->status,
        ];
    }
}
