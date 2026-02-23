<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ActionRun;
use App\Models\BlockOption;
use App\Models\Conversation;
use App\Models\Endpoint;
use App\Models\Message;
use App\Support\ChatConstants;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Executes API_CALL: builds request from endpoint + context/customer/selection, runs HTTP, redacts, maps response, creates ActionRun.
 */
final class ActionRunner
{
    public function __construct(
        private readonly ResponseMapper $responseMapper
    ) {}

    /**
     * Run the API action for the given conversation, option, and optional message. Returns ActionRun and success flag.
     *
     * @return array{action_run: ActionRun, success: bool}
     */
    public function run(Conversation $conversation, BlockOption $option, ?int $messageId = null): array
    {
        $endpoint = $option->endpoint;
        if (! $endpoint) {
            $run = ActionRun::create([
                'conversation_id' => $conversation->id,
                'message_id' => $messageId,
                'block_option_id' => $option->id,
                'endpoint_id' => null,
                'status' => ChatConstants::RUN_STATUS_FAILED,
                'error_message' => 'No endpoint configured for this option.',
            ]);

            return ['action_run' => $run, 'success' => false];
        }

        $run = ActionRun::create([
            'conversation_id' => $conversation->id,
            'message_id' => $messageId,
            'block_option_id' => $option->id,
            'endpoint_id' => $endpoint->id,
            'status' => ChatConstants::RUN_STATUS_RUNNING,
        ]);

        $start = microtime(true);
        $payload = $this->buildPayload($conversation, $option, $endpoint);
        $requestBody = $payload;
        $redactedRequest = $this->redact($requestBody);

        try {
            $headers = $endpoint->getDecryptedHeaders();
            $authConfig = $endpoint->getDecryptedAuthConfig();
            if (! empty($authConfig['bearer_token'] ?? null)) {
                $headers['Authorization'] = 'Bearer '.$authConfig['bearer_token'];
            }
            $request = Http::withHeaders($headers)->timeout($endpoint->timeout_sec);
            $method = strtoupper($endpoint->method);
            $response = in_array($method, ['GET', 'HEAD'], true)
                ? $request->send($method, $endpoint->url)
                : $request->withBody(json_encode($payload), 'application/json')->send($method, $endpoint->url);

            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $body = $response->json();
            $bodyArray = is_array($body) ? $body : [];
            $redactedResponse = $this->redact($bodyArray);

            $run->update([
                'status' => $response->successful() ? ChatConstants::RUN_STATUS_SUCCESS : ChatConstants::RUN_STATUS_FAILED,
                'request_redacted' => json_encode($redactedRequest),
                'response_redacted' => json_encode($redactedResponse),
                'http_code' => $response->status(),
                'duration_ms' => $durationMs,
                'error_message' => $response->successful() ? null : ($bodyArray['message'] ?? $response->body()),
            ]);

            Log::channel('actions')->info('Action run completed', [
                'action_run_id' => $run->id,
                'status' => $run->status,
                'http_code' => $run->http_code,
            ]);

            return [
                'action_run' => $run,
                'success' => $response->successful(),
                'response_body' => $bodyArray,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $run->update([
                'status' => ChatConstants::RUN_STATUS_FAILED,
                'request_redacted' => json_encode($redactedRequest),
                'response_redacted' => null,
                'http_code' => null,
                'duration_ms' => $durationMs,
                'error_message' => $e->getMessage(),
            ]);
            Log::channel('actions')->error('Action run failed', ['action_run_id' => $run->id, 'error' => $e->getMessage()]);

            return ['action_run' => $run, 'success' => false, 'response_body' => []];
        }
    }

    /**
     * Build request payload from endpoint request_mapper and conversation context, customer, option.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(Conversation $conversation, BlockOption $option, Endpoint $endpoint): array
    {
        $mapper = $endpoint->request_mapper ?? [];
        $context = $conversation->getContextArray();
        $customer = $conversation->customer;
        $payload = [];
        foreach ($mapper as $key => $source) {
            if (is_string($source)) {
                if (str_starts_with($source, 'context.')) {
                    $payload[$key] = $context[substr($source, 8)] ?? null;
                } elseif (str_starts_with($source, 'customer.')) {
                    $payload[$key] = $customer->getAttribute(substr($source, 9));
                } elseif ($source === 'option_label') {
                    $payload[$key] = $option->label;
                } else {
                    $payload[$key] = $context[$source] ?? $source;
                }
            }
        }

        return $payload ?: ['context' => $context, 'customer_id' => $customer->id];
    }

    /**
     * Redact sensitive keys from array (e.g. Authorization, password, token).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        $sensitive = ['authorization', 'password', 'token', 'api_key', 'secret'];
        $out = [];
        foreach ($data as $k => $v) {
            $lower = strtolower((string) $k);
            $redact = false;
            foreach ($sensitive as $s) {
                if (str_contains($lower, $s)) {
                    $redact = true;
                    break;
                }
            }
            $out[$k] = $redact ? '[REDACTED]' : (is_array($v) ? $this->redact($v) : $v);
        }

        return $out;
    }
}
