<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Flow;
use App\Services\Chat\BlockPresenter;
use App\Services\Chat\ConversationOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customer-facing chat API: start conversation, send message, click option, SSE stream.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly ConversationOrchestrator $orchestrator,
        private readonly BlockPresenter $blockPresenter
    ) {}

    /**
     * Start a new conversation. Creates customer if needed, creates conversation, returns initial block.
     *
     * Body: customer_id (optional, existing), or email/name for new customer. flow_key (optional).
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'email' => 'nullable|email',
            'name' => 'nullable|string|max:255',
            'flow_key' => 'nullable|string|max:64',
        ]);

        $tenantId = $request->input('tenant_id');
        $customerId = $request->input('customer_id');
        if (! $customerId) {
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'external_id' => $request->input('external_id'),
                'email' => $request->input('email'),
                'name' => $request->input('name'),
                'meta' => $request->only(['external_id']),
            ]);
        } else {
            $customer = Customer::findOrFail($customerId);
        }

        $flowKey = $request->input('flow_key', config('chat.default_flow_key'));
        $flow = Flow::where('key', $flowKey)->where('active', true)->first();
        if (! $flow) {
            $flow = Flow::where('active', true)->first();
        }
        if (! $flow) {
            return response()->json(['error' => 'No active flow found.'], 422);
        }

        $firstStep = $flow->steps()->orderBy('id')->first();
        $conversation = Conversation::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'flow_id' => $flow->id,
            'current_step_id' => $firstStep?->id,
            'status' => 'active',
        ]);

        $presented = $this->blockPresenter->present($conversation);
        $conversation->update(['last_presented_block_key' => $presented['block_key']]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'messages' => [],
            'block' => $presented,
        ]);
    }

    /**
     * Send a free-text message. Returns new messages and updated block.
     */
    public function message(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:4096']);
        $this->ensureConversationActive($conversation);

        $result = $this->orchestrator->process($conversation, [
            'input_type' => 'text',
            'message' => $request->input('message'),
        ]);

        return response()->json([
            'messages' => $result['messages'],
            'block' => $result['block'],
            'action_status' => $result['action_status'],
        ]);
    }

    /**
     * Click an option (button). Pass either option_id (block option) or step_option_id (step suggestion).
     */
    public function option(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'option_id' => 'required_without:step_option_id|nullable|integer|exists:block_options,id',
            'step_option_id' => 'required_without:option_id|nullable|integer|exists:step_options,id',
        ]);
        if ($request->input('option_id') && $request->input('step_option_id')) {
            return response()->json(['error' => 'Provide either option_id or step_option_id, not both.'], 422);
        }
        $this->ensureConversationActive($conversation);

        $input = ['input_type' => 'option_click'];
        if ($request->filled('step_option_id')) {
            $input['step_option_id'] = (int) $request->input('step_option_id');
        } else {
            $input['option_id'] = (int) $request->input('option_id');
        }

        $result = $this->orchestrator->process($conversation, $input);

        return response()->json([
            'messages' => $result['messages'],
            'block' => $result['block'],
            'action_status' => $result['action_status'],
        ]);
    }

    /**
     * SSE stream for real-time updates (messages, block, action_status).
     */
    public function stream(Request $request, Conversation $conversation): StreamedResponse|JsonResponse
    {
        $this->ensureConversationActive($conversation);

        return response()->stream(function () use ($conversation) {
            $lastId = (int) request()->query('last_message_id', 0);
            $timeout = 0;
            while (true) {
                $messages = $conversation->messages()->where('id', '>', $lastId)->orderBy('id')->get();
                foreach ($messages as $msg) {
                    echo 'data: '.json_encode(['type' => 'message', 'message' => ['id' => $msg->id, 'role' => $msg->role, 'content' => $msg->content]])."\n\n";
                    $lastId = $msg->id;
                }
                $runs = $conversation->actionRuns()->where('updated_at', '>=', now()->subSeconds(30))->latest()->get();
                foreach ($runs as $run) {
                    echo 'data: '.json_encode(['type' => 'action_status', 'action_run' => ['id' => $run->id, 'status' => $run->status]])."\n\n";
                }
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
                if (connection_aborted()) {
                    break;
                }
                sleep(2);
                $timeout += 2;
                if ($timeout >= 60) {
                    echo "data: {\"type\":\"heartbeat\"}\n\n";
                    flush();
                    $timeout = 0;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function ensureConversationActive(Conversation $conversation): void
    {
        if ($conversation->status === 'closed') {
            abort(404, 'Conversation is closed.');
        }
    }
}
