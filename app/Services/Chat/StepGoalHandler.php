<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Step;
use App\Services\OpenRouter\OpenRouterClient;
use App\Support\ChatConstants;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * For steps with a goal: handles one user message by asking the AI to extract variables,
 * generate the next bot message, and report whether the goal is achieved.
 * The conversation stays in the same step until goal_achieved is true.
 */
final class StepGoalHandler
{
    private const LAST_MESSAGES_LIMIT = 10;

    public function __construct(
        private readonly OpenRouterClient $client
    ) {}

    /**
     * Handle one user message in goal mode. Returns variables to merge into context, bot message, and goal_achieved flag.
     *
     * @return array{variables: array<string, mixed>, bot_message: string, goal_achieved: bool}
     */
    public function handle(Conversation $conversation, Step $step, string $userMessageText): array
    {
        $context = $conversation->getContextArray();
        $contextVarSpec = $this->getContextVariableSpecForStep($step);
        $recentMessages = $this->getRecentMessagesForPrompt($conversation);

        $systemPrompt = $this->buildSystemPrompt($step);
        $userPrompt = $this->buildUserPrompt($step, $contextVarSpec, $context, $recentMessages, $userMessageText);

        $model = $step->ai_model_override ?? $step->flow->default_model ?? OpenRouterClient::getDefaultModel();
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        Log::channel('chat')->info('StepGoalHandler request', ['conversation_id' => $conversation->id, 'step_key' => $step->key]);

        $response = $this->client->chat($messages, ['model' => $model]);
        $content = $response['content'] ?? null;

        $parsed = $this->parseResponse($content);
        $variables = $parsed['variables'];
        $botMessage = $parsed['bot_message'];
        $goalAchieved = $parsed['goal_achieved'];

        // Merge variables into a copy of context to validate goal_achieved
        $updatedContext = $context;
        foreach ($variables as $k => $v) {
            if ($v !== null && $v !== '') {
                $updatedContext[$k] = $v;
            }
        }

        $requiredKeys = $this->getContextVariableKeysForStep($step);
        if ($requiredKeys !== [] && $this->allRequiredKeysFilled($updatedContext, $requiredKeys)) {
            $goalAchieved = true;
        }

        return [
            'variables' => $variables,
            'bot_message' => $botMessage,
            'goal_achieved' => $goalAchieved,
        ];
    }

    private function buildSystemPrompt(Step $step): string
    {
        $base = 'You are a helpful support assistant in a chat. Your task is to work toward a single step goal: collect the required information from the user through natural conversation. Reply in English. Output only valid JSON.';
        $toneDescription = ChatConstants::toneDescriptionForPrompt($step->tone, $step->tone_instructions);
        if ($toneDescription !== null && Str::length(trim($toneDescription)) > 0) {
            $base .= "\n\nTone: " . trim($toneDescription) . ' Apply this tone to your bot_message.';
        }
        return $base;
    }

    private function buildUserPrompt(Step $step, array $contextVarSpec, array $currentContext, array $recentMessages, string $userMessageText): string
    {
        $goal = trim((string) $step->goal);
        $parts = [
            "Step goal: " . $goal,
            '',
            "Context variables to collect (save under these keys; use exactly these keys in the 'variables' object):",
        ];
        if ($contextVarSpec !== []) {
            foreach ($contextVarSpec as $key => $label) {
                $parts[] = "  - " . $key . ($label !== '' ? " ({$label})" : '');
            }
        } else {
            $parts[] = "  (none defined; extract any relevant data from the user message into 'variables' with sensible keys.)";
        }
        $parts[] = '';
        $parts[] = "Current conversation context (already collected): " . (empty($currentContext) ? 'none' : json_encode($currentContext));
        $parts[] = '';

        if ($recentMessages !== []) {
            $parts[] = "Recent messages (role: content):";
            foreach ($recentMessages as $msg) {
                $parts[] = "  " . $msg['role'] . ": " . $msg['content'];
            }
            $parts[] = '';
        }

        $parts[] = "Latest user message: " . $userMessageText;
        $parts[] = '';
        $parts[] = "Respond with a single JSON object with exactly these keys:";
        $parts[] = '  - variables: object with the keys listed above; set each to the value extracted from the user message (or empty string if not provided). Only include keys you can extract from this message.';
        $parts[] = '  - bot_message: one short reply from the bot (e.g. ask for the next missing piece, thank the user, or confirm that you have what you need). Do NOT repeat or paraphrase the user; ask for the next needed info or confirm.';
        $parts[] = '  - goal_achieved: boolean. Set to true only when the step goal is fully satisfied (e.g. all required context variables are now filled). Otherwise false.';

        return implode("\n", $parts);
    }

    /**
     * @return array<string, string> key => label
     */
    private function getContextVariableSpecForStep(Step $step): array
    {
        if (! is_array($step->context_variables)) {
            return [];
        }
        $spec = [];
        foreach ($step->context_variables as $item) {
            if (is_array($item) && ! empty($item['key'])) {
                $spec[$item['key']] = trim((string) ($item['label'] ?? ''));
            }
        }
        return $spec;
    }

    /**
     * @return array<int, string>
     */
    private function getContextVariableKeysForStep(Step $step): array
    {
        if (! is_array($step->context_variables)) {
            return [];
        }
        $keys = [];
        foreach ($step->context_variables as $item) {
            if (is_array($item) && ! empty($item['key'])) {
                $keys[] = $item['key'];
            }
        }
        return $keys;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function getRecentMessagesForPrompt(Conversation $conversation): array
    {
        $messages = $conversation->messages()
            ->latest('id')
            ->limit(self::LAST_MESSAGES_LIMIT)
            ->get()
            ->reverse();

        return $messages->map(fn ($m) => [
            'role' => $m->role,
            'content' => (string) ($m->content ?? ''),
        ])->all();
    }

    /**
     * @return array{variables: array<string, mixed>, bot_message: string, goal_achieved: bool}
     */
    private function parseResponse(?string $content): array
    {
        $default = [
            'variables' => [],
            'bot_message' => 'I didn\'t quite get that. Could you please try again?',
            'goal_achieved' => false,
        ];

        if ($content === null || trim($content) === '') {
            return $default;
        }

        $content = trim($content);
        // Strip markdown code block if present
        if (Str::startsWith($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return $default;
        }

        $variables = is_array($decoded['variables'] ?? null) ? $decoded['variables'] : [];
        $botMessage = isset($decoded['bot_message']) && is_string($decoded['bot_message']) && $decoded['bot_message'] !== ''
            ? trim($decoded['bot_message'])
            : $default['bot_message'];
        $goalAchieved = (bool) ($decoded['goal_achieved'] ?? false);

        return [
            'variables' => $variables,
            'bot_message' => $botMessage,
            'goal_achieved' => $goalAchieved,
        ];
    }

    /**
     * @param array<int, string> $requiredKeys
     */
    private function allRequiredKeysFilled(array $context, array $requiredKeys): bool
    {
        foreach ($requiredKeys as $key) {
            $v = $context[$key] ?? null;
            if ($v === null || (is_string($v) && trim($v) === '')) {
                return false;
            }
        }
        return true;
    }
}
