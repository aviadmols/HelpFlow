<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockResource\Pages;

use App\Filament\Resources\BlockResource;
use App\Services\OpenRouter\OpenRouterClient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBlock extends CreateRecord
{
    protected static string $resource = BlockResource::class;

    /**
     * Call OpenRouter to suggest a block message template from title/key; fill the form field.
     */
    public function suggestMessageWithAi(): void
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
        $title = $state['title'] ?? $state['key'] ?? 'this option';
        $key = $state['key'] ?? '';

        $systemPrompt = 'You suggest a short, friendly customer support bot message. One or two sentences in English only. No JSON, no quotes—just the message text.';
        $userPrompt = sprintf('Block title: %s. Key: %s. Suggest the message the bot should show when this block is displayed.', $title, $key ?: '(not set)');

        $response = $client->chat(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['max_tokens' => 150]
        );

        $content = trim($response['content'] ?? '');
        if ($content === '') {
            Notification::make()->title('No suggestion returned')->warning()->send();
            return;
        }

        $this->form->fill(['message_template' => $content]);
        Notification::make()
            ->title('Suggestion applied')
            ->body('Message template filled. You can edit it before saving.')
            ->success()
            ->send();
    }
}
