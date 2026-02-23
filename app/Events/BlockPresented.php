<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new option block is presented to the user. Broadcasts to chat.{conversationId}.
 */
class BlockPresented implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{bot_message: string, block_key: string, options: array}  $block
     */
    public function __construct(
        public readonly Conversation $conversation,
        public readonly array $block
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('chat.'.$this->conversation->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'block.presented';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'block' => $this->block,
        ];
    }
}
