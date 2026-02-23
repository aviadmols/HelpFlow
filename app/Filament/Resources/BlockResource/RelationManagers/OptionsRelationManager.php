<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockResource\RelationManagers;

use App\Models\Step;
use App\Services\OpenRouter\OpenRouterClient;
use App\Support\ChatConstants;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    /** Returns step options (key => id) for the block's flows or all steps. */
    private function stepOptions(): array
    {
        $block = $this->getOwnerRecord();
        $flowIds = $block->tenant?->flows()->pluck('id') ?? collect();

        return Step::whereIn('flow_id', $flowIds)->orWhereHas('flow')->orderBy('id')->pluck('key', 'id')->all();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('What the customer sees')
                    ->description('Label on the button and optional one-line bot reply when they click it.')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(255),
                        TextInput::make('bot_reply')
                            ->label('Bot reply for this option (optional)')
                            ->maxLength(512)
                            ->placeholder('e.g. I\'ll help you with that.')
                            ->helperText('One short line shown to the customer when they click this option, before the action runs. Leave empty to use the next block message.'),
                        Placeholder::make('suggest_ai')
                            ->label('')
                            ->content(new HtmlString(
                                '<button type="button" wire:click="suggestOptionWithAi" class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Suggest with AI</button>'
                            )),
                    ])
                    ->columns(1),
                Section::make('What runs (action)')
                    ->description('Choose the action type and configure endpoint, step, or prompt.')
                    ->schema([
                Select::make('action_type')
                    ->label('Action type')
                    ->options(collect(ChatConstants::actionTypes())->mapWithKeys(fn ($v) => [$v => $v])->all())
                    ->required()
                    ->live()
                    ->helperText('Choose what runs: Endpoint, Step, Confirm step, Handoff, or AI prompt (ChatGPT/OpenRouter).'),
                Select::make('endpoint_id')
                    ->label('Endpoint')
                    ->relationship('endpoint', 'name')
                    ->searchable()
                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL)
                    ->nullable()
                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL)
                    ->helperText('API to call when this option is selected.'),
                Textarea::make('success_template')
                    ->label('Success template')
                    ->rows(2)
                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL),
                Textarea::make('failure_template')
                    ->label('Failure template')
                    ->rows(2)
                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL),
                Select::make('next_step_id')
                    ->label('Next step')
                    ->options(fn () => $this->stepOptions())
                    ->searchable()
                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_NEXT_STEP)
                    ->nullable()
                    ->visible(fn (Get $get) => in_array($get('action_type'), [ChatConstants::ACTION_TYPE_API_CALL, ChatConstants::ACTION_TYPE_NEXT_STEP, ChatConstants::ACTION_TYPE_RUN_PROMPT], true))
                    ->helperText('For API_CALL: step after success. For NEXT_STEP: step to go to. For RUN_PROMPT: optional step after showing AI response.'),
                Select::make('next_step_on_failure_id')
                    ->label('Next step on failure')
                    ->options(fn () => $this->stepOptions())
                    ->searchable()
                    ->nullable()
                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL),
                Select::make('confirm_step_id')
                    ->label('Confirmation step')
                    ->options(fn () => $this->stepOptions())
                    ->searchable()
                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_CONFIRM)
                    ->nullable()
                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_CONFIRM)
                    ->helperText('Step that asks the user to confirm (e.g. Yes/No).'),
                Textarea::make('prompt_template')
                    ->label('AI prompt (ChatGPT/OpenRouter)')
                    ->rows(4)
                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_RUN_PROMPT)
                    ->nullable()
                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_RUN_PROMPT)
                    ->placeholder('e.g. Explain in one short sentence what the user can do with their subscription. Customer name: {{customer.name}}')
                    ->helperText('Prompt sent to the AI. Use {{variable}} from conversation context. Response is shown to the user.'),
                TextInput::make('sort_order')->label('Sort order')->numeric()->default(0),
                    ])
                    ->columns(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->modifyQueryUsing(fn ($query) => $query->with(['endpoint', 'nextStep', 'confirmStep']))
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('action_type')->label('Action type'),
                TextColumn::make('triggers_summary')->label('Runs')->placeholder('—'),
                TextColumn::make('sort_order'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    /**
     * Call OpenRouter to suggest option label and bot reply; fill the form with the result.
     */
    public function suggestOptionWithAi(): void
    {
        try {
            $client = app(OpenRouterClient::class);
        } catch (\Throwable) {
            Notification::make()
                ->title('OpenRouter not configured')
                ->body('Set your API token in Settings → OpenRouter / API.')
                ->warning()
                ->send();

            return;
        }

        $block = $this->getOwnerRecord();
        $blockTitle = $block->title ?? $block->key ?? 'Option';
        $currentLabel = $this->form->getState()['label'] ?? '';
        $currentBotReply = $this->form->getState()['bot_reply'] ?? '';

        $systemPrompt = 'You suggest customer support chat option text. Return only valid JSON with keys "label" and "bot_reply". label: short button text (e.g. "Cancel subscription"). bot_reply: one short sentence the bot says when the user clicks (e.g. "I\'ll help you cancel."). Use English only.';
        $userPrompt = sprintf(
            'Block title: %s. Current option label: %s. Suggest a clear "label" and "bot_reply" for this option. Return only JSON, no other text.',
            $blockTitle,
            $currentLabel ?: '(not set)'
        );

        $response = $client->chat(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['max_tokens' => 150]
        );

        $content = $response['content'] ?? null;
        if ($content === null || $content === '') {
            Notification::make()->title('No suggestion returned')->warning()->send();

            return;
        }

        $content = trim($content);
        if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            $content = $m[0];
        }
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            Notification::make()->title('Could not parse AI response')->body('Response was not valid JSON.')->warning()->send();

            return;
        }

        $label = isset($decoded['label']) && is_string($decoded['label']) ? trim($decoded['label']) : $currentLabel;
        $botReply = isset($decoded['bot_reply']) && is_string($decoded['bot_reply']) ? trim($decoded['bot_reply']) : $currentBotReply;

        $this->form->fill([
            'label' => $label,
            'bot_reply' => $botReply,
        ]);

        Notification::make()
            ->title('Suggestion applied')
            ->body('Label and bot reply have been filled. You can edit them before saving.')
            ->success()
            ->send();
    }
}
