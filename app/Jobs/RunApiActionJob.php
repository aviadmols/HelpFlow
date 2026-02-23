<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BlockOption;
use App\Models\Conversation;
use App\Models\StepOption;
use App\Services\Chat\ActionRunner;
use App\Services\Chat\ResponseMapper;
use App\Services\Chat\TemplateRenderer;
use App\Support\ChatConstants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued job: runs API action for an option click (block or step option), then updates context and posts bot message.
 */
class RunApiActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $optionId,
        public readonly ?int $messageId = null,
        public readonly string $optionSource = 'block'
    ) {}

    /**
     * Execute the job: run action, map response to context, create bot message, transition step on success/failure.
     */
    public function handle(ActionRunner $actionRunner, ResponseMapper $responseMapper, TemplateRenderer $renderer): void
    {
        $conversation = Conversation::find($this->conversationId);
        if (! $conversation) {
            return;
        }

        $option = $this->optionSource === 'step'
            ? StepOption::with('endpoint', 'nextStep', 'nextStepOnFailure')->find($this->optionId)
            : BlockOption::with('endpoint', 'nextStep', 'nextStepOnFailure')->find($this->optionId);

        if (! $option) {
            return;
        }

        $result = $actionRunner->run($conversation, $option, $this->messageId);
        $success = $result['success'];
        $responseBody = $result['response_body'] ?? [];

        $ctx = $conversation->getContextArray();
        if ($option->endpoint && $option->endpoint->response_mapper && $success && is_array($responseBody)) {
            $mapped = $responseMapper->map($option->endpoint->response_mapper, $responseBody);
            foreach ($mapped as $k => $v) {
                $ctx[$k] = $v;
            }
            $conversation->setContextArray($ctx);
        }

        $template = $success ? ($option->success_template ?? 'Done.') : ($option->failure_template ?? 'Something went wrong. Please try again or contact support.');
        $content = $renderer->render($template, $ctx);

        \App\Models\Message::create([
            'conversation_id' => $conversation->id,
            'role' => ChatConstants::MESSAGE_ROLE_BOT,
            'content' => $content,
            'message_type' => ChatConstants::MESSAGE_TYPE_TEXT,
        ]);

        if ($success && $option->next_step_id) {
            $conversation->update(['current_step_id' => $option->next_step_id]);
        } elseif (! $success && $option->next_step_on_failure_id) {
            $conversation->update(['current_step_id' => $option->next_step_on_failure_id]);
        }
        $conversation->save();
    }
}
