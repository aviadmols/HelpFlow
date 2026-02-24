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
        $requiredKeys = $this->getContextVariableKeysForStep($step);
        // Only accept variables for keys defined on the step (exact key match)
        $variables = $this->filterVariablesToStepKeys($parsed['variables'], $requiredKeys);
        $botMessage = $parsed['bot_message'];
        $goalAchieved = $parsed['goal_achieved'];

        // Merge variables into a copy of context to validate goal_achieved
        $updatedContext = $context;
        foreach ($variables as $k => $v) {
            if ($v !== null && $v !== '') {
                $updatedContext[$k] = $v;
            }
        }

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
        $base = 'You are a helpful support assistant in a chat. Your task is to collect the required information from the user step by step. You MUST: (1) Extract from the user message any values that match the context variable keys (e.g. if the user says "Aviad" or "John", set the name variable; if they give an email, set the email variable). (2) In bot_message, ask ONLY for the NEXT piece of information that is still missing—never ask again for something already in "Current conversation context". (3) Use exactly the keys provided for the variables object. Reply in English. Output only valid JSON.';
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
        if ($contextVarSpec !== []) {
            $stillNeeded = [];
            foreach (array_keys($contextVarSpec) as $key) {
                $val = $currentContext[$key] ?? null;
                if ($val === null || (is_string($val) && trim($val) === '')) {
                    $stillNeeded[] = $key;
                }
            }
            $parts[] = "Still needed (ask for these only): " . (empty($stillNeeded) ? 'none' : implode(', ', $stillNeeded));
        }
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
        $parts[] = '  - variables: object using ONLY the context variable keys listed above. Set each key to the value extracted from the latest user message (e.g. if user says "Aviad", set name to "Aviad"; if user says an email, set email). Use empty string only for keys not provided in this message.';
        $parts[] = '  - bot_message: one short reply. If something is still missing from context, ask for the NEXT missing item only (e.g. if name is already in context, ask for email). Never ask again for data already in "Current conversation context".';
        $parts[] = '  - goal_achieved: true only when every context variable is now filled; otherwise false.';

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
     * Keep only variables whose keys are in the step's context_variables. Matches keys case-insensitively so "Name" is accepted when step has "name".
     *
     * @param array<int, string> $allowedKeys
     * @return array<string, mixed>
     */
    private function filterVariablesToStepKeys(array $variables, array $allowedKeys): array
    {
        if ($allowedKeys === []) {
            return $variables;
        }
        $canonicalByLower = [];
        foreach ($allowedKeys as $key) {
            $canonicalByLower[strtolower((string) $key)] = $key;
        }
        $filtered = [];
        foreach ($variables as $k => $v) {
            $canonical = is_string($k) ? ($canonicalByLower[strtolower($k)] ?? null) : null;
            if ($canonical !== null) {
                $filtered[$canonical] = $v;
            }
        }
        return $filtered;
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
