<?php

declare(strict_types=1);

namespace App\Filament\Resources\EndpointResource\Pages;

use App\Filament\Resources\EndpointResource;
use App\Services\OpenRouter\OpenRouterClient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEndpoint extends CreateRecord
{
    protected static string $resource = EndpointResource::class;

    /**
     * Call OpenRouter to suggest request_mapper and response_mapper from endpoint name/url; fill the form.
     */
    public function suggestMappersWithAi(): void
    {
        try {
            $client = app(OpenRouterClient::class);
        } catch (\Throwable) {
            Notification::make()
                ->title('OpenRouter not configured')
                ->body('Set your API token in Settings → OpenRouter.')
                ->warning()
                ->send();
            return;
        }

        $state = $this->form->getState();
        $name = $state['name'] ?? $state['key'] ?? 'API';
        $url = $state['url'] ?? '';
        $method = $state['method'] ?? 'POST';

        $systemPrompt = 'You suggest variable mappings for an API endpoint used in a chat flow. Return only valid JSON with two keys: "request_mapper" (object: variable name => source like context.email, context.order_number) and "response_mapper" (object: variable name => JSON path like data.code or result.discount_code). Use English keys.';
        $userPrompt = sprintf(
            'Endpoint name: %s. URL: %s. Method: %s. Suggest request_mapper (where to get payload from conversation context) and response_mapper (how to extract variables from the API JSON response). Return only JSON.',
            $name,
            $url ?: '(not set)',
            $method
        );

        $response = $client->chat(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['max_tokens' => 350]
        );

        $content = trim($response['content'] ?? '');
        if ($content === '') {
            Notification::make()->title('No suggestion returned')->warning()->send();
            return;
        }
        if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            $content = $m[0];
        }
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            Notification::make()->title('Could not parse AI response')->body('Response was not valid JSON.')->warning()->send();
            return;
        }

        $requestMapper = isset($decoded['request_mapper']) && is_array($decoded['request_mapper']) ? $decoded['request_mapper'] : [];
        $responseMapper = isset($decoded['response_mapper']) && is_array($decoded['response_mapper']) ? $decoded['response_mapper'] : [];
        $this->form->fill([
            'request_mapper' => $requestMapper,
            'response_mapper' => $responseMapper,
        ]);
        Notification::make()
            ->title('Suggestion applied')
            ->body('Request and response mappers filled. You can edit them before saving.')
            ->success()
            ->send();
    }
}
