<?php

declare(strict_types=1);

namespace App\Services\OpenRouter;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for OpenRouter API (OpenAI-compatible). Sends chat messages and returns raw response with optional token usage.
 */
final class OpenRouterClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSec = 30
    ) {}

    /**
     * Send chat completion request to OpenRouter.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array{model?: string, temperature?: float, max_tokens?: int, top_p?: float}  $options
     * @return array{content?: string, usage?: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}, raw: array}
     */
    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? self::getDefaultModel();
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? config('openrouter.temperature'),
            'max_tokens' => $options['max_tokens'] ?? config('openrouter.max_tokens'),
            'top_p' => $options['top_p'] ?? config('openrouter.top_p'),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'HTTP-Referer' => config('app.url', ''),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->timeoutSec)
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);

        $raw = $response->json();
        if (! is_array($raw)) {
            return ['content' => null, 'usage' => null, 'raw' => []];
        }

        $content = null;
        $choices = $raw['choices'] ?? [];
        if (isset($choices[0]['message']['content'])) {
            $content = $choices[0]['message']['content'];
        }

        $usage = $raw['usage'] ?? null;
        $usageArray = null;
        if (is_array($usage)) {
            $usageArray = [
                'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
            ];
        }

        return [
            'content' => $content,
            'usage' => $usageArray,
            'raw' => $raw,
        ];
    }

    /**
     * Effective default model: admin setting overrides .env when set.
     */
    public static function getDefaultModel(): string
    {
        $fromSetting = Setting::get('openrouter_default_model');
        if ($fromSetting !== null && $fromSetting !== '') {
            return $fromSetting;
        }

        return config('openrouter.default_model', 'openai/gpt-4o-mini');
    }

    /**
     * Create client from config. API key: admin setting overrides .env when set.
     */
    public static function fromConfig(?string $apiKey = null, ?string $baseUrl = null): self
    {
        $resolvedKey = $apiKey ?? Setting::get('openrouter_api_key') ?? config('openrouter.api_key', '');

        return new self(
            $baseUrl ?? config('openrouter.base_url'),
            $resolvedKey,
            (int) config('openrouter.timeout_sec', 30)
        );
    }
}
