<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlowResource\RelationManagers;

use App\Models\Block;
use App\Models\Endpoint;
use App\Models\StepOption;
use App\Services\OpenRouter\OpenRouterClient;
use App\Support\ChatConstants;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return 'Steps';
    }

    public function form(Form $form): Form
    {
        $flow = $this->getOwnerRecord();
        $stepOptions = $flow->steps()->pluck('key', 'id')->all();
        $stepKeyOptions = $flow->steps()->pluck('key', 'key')->all();
        $blockKeyOptions = Block::query()->get()->mapWithKeys(fn ($b) => [$b->key => $b->title ?? $b->key])->all();

        return $form
            ->schema([
                TextInput::make('key')->required()->maxLength(64),
                Textarea::make('bot_message_template')
                    ->label('Bot message to customer')
                    ->rows(3)
                    ->columnSpanFull(),
                Placeholder::make('suggest_bot_message_ai')
                    ->label('')
                    ->content(new HtmlString(
                        '<button type="button" wire:click="suggestBotMessageWithAi" class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Suggest bot message with AI</button>'
                    )),
                Section::make('Next steps')
                    ->description('Which steps the conversation can move to from this step. Leave empty to allow any step in the flow.')
                    ->schema([
                        Select::make('allowed_next_step_ids')
                            ->label('Allowed next steps')
                            ->options($stepOptions)
                            ->multiple()
                            ->searchable()
                            ->helperText('Only these steps can be reached from this step. Empty = all flow steps allowed.'),
                    ])
                    ->collapsible()
                    ->collapsed(false),
                Section::make('Expected answers (transition rules)')
                    ->description('Define intents or keywords that map to a target step or block. Used to guide the AI or for direct matching before calling the AI.')
                    ->schema([
                        Repeater::make('transition_rules')
                            ->schema([
                                TextInput::make('intent')
                                    ->label('Intent / keywords')
                                    ->placeholder('e.g. cancel, update order')
                                    ->required(),
                                Select::make('target_step_key')
                                    ->label('Target step')
                                    ->options($stepKeyOptions)
                                    ->nullable(),
                                Select::make('target_block_key')
                                    ->label('Target block')
                                    ->options($blockKeyOptions)
                                    ->nullable(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['intent'] ?? null),
                    ])
                    ->collapsible()
                    ->collapsed(false),
                Section::make('Suggestions (options for this step)')
                    ->description('Buttons shown to the customer in this step. When set, these replace block-based options. What to send: map context to request body. What we receive: use success/failure template with variables from the endpoint response mapping.')
                    ->schema([
                        Repeater::make('stepOptions')
                            ->relationship(
                                modifyQueryUsing: fn ($query) => $query->orderBy('sort_order')->orderBy('id')
                            )
                            ->schema([
                                TextInput::make('label')->required()->maxLength(255),
                                TextInput::make('bot_reply')
                                    ->label('Bot reply (optional)')
                                    ->maxLength(512)
                                    ->nullable(),
                                Select::make('action_type')
                                    ->label('Action type')
                                    ->options(collect(ChatConstants::actionTypes())->mapWithKeys(fn ($v) => [$v => $v])->all())
                                    ->required()
                                    ->live(),
                                Select::make('endpoint_id')
                                    ->label('Endpoint')
                                    ->relationship('endpoint', 'name')
                                    ->searchable()
                                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL)
                                    ->nullable()
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL),
                                KeyValue::make('payload_mapper')
                                    ->label('What to send (payload mapping)')
                                    ->keyLabel('Request key')
                                    ->valueLabel('Context source (e.g. context.email)')
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL)
                                    ->helperText('Map context/customer to request body. Endpoint request_mapper is applied first.'),
                                Textarea::make('success_template')
                                    ->label('Success template (what we receive)')
                                    ->rows(2)
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL),
                                Textarea::make('failure_template')
                                    ->label('Failure template')
                                    ->rows(2)
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL),
                                Select::make('next_step_id')
                                    ->label('Next step')
                                    ->options($stepOptions)
                                    ->searchable()
                                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_NEXT_STEP)
                                    ->nullable()
                                    ->visible(fn (Get $get) => in_array($get('action_type'), [ChatConstants::ACTION_TYPE_API_CALL, ChatConstants::ACTION_TYPE_NEXT_STEP, ChatConstants::ACTION_TYPE_RUN_PROMPT], true)),
                                Select::make('next_step_on_failure_id')
                                    ->label('Next step on failure')
                                    ->options($stepOptions)
                                    ->searchable()
                                    ->nullable()
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL),
                                Select::make('confirm_step_id')
                                    ->label('Confirmation step')
                                    ->options($stepOptions)
                                    ->searchable()
                                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_CONFIRM)
                                    ->nullable()
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_CONFIRM),
                                Textarea::make('prompt_template')
                                    ->label('AI prompt (RUN_PROMPT)')
                                    ->rows(3)
                                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_RUN_PROMPT)
                                    ->nullable()
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_RUN_PROMPT),
                                TextInput::make('sort_order')->label('Sort order')->numeric()->default(0),
                            ])
                            ->columns(1)
                            ->collapsible()
                            ->itemLabel(fn ($state) => $state['label'] ?? 'Suggestion')
                            ->reorderable()
                            ->visible(fn () => (bool) $this->getMountedTableActionRecord()?->getKey()),
                    ])
                    ->visible(fn () => (bool) $this->getMountedTableActionRecord()?->getKey())
                    ->collapsible()
                    ->collapsed(false),
                Section::make('Blocks & fallback')
                    ->description('Blocks the customer can be directed to in this step. If the AI does not recognize the intent, the fallback block is shown.')
                    ->schema([
                        Select::make('allowed_block_ids')
                            ->label('Allowed blocks')
                            ->options(Block::query()->pluck('title', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->helperText('Only these blocks can be shown in this step. Leave empty to allow any block.'),
                        Select::make('fallback_block_id')
                            ->label('Fallback block (when AI doesn\'t recognize)')
                            ->options(Block::query()->pluck('title', 'id')->all())
                            ->searchable()
                            ->nullable()
                            ->helperText('Block to show when confidence is low or intent is unclear.'),
                        Select::make('order_lookup_endpoint_id')
                            ->label('Order lookup endpoint (optional)')
                            ->options(Endpoint::query()->pluck('name', 'id')->all())
                            ->searchable()
                            ->nullable()
                            ->helperText('For collect steps: after extracting email/order_number from the user message, call this endpoint and merge the response into context.'),
                    ])
                    ->columns(1),
                Section::make('AI routing (this step)')
                    ->description('Override flow prompts for this step. Leave empty to use flow defaults.')
                    ->schema([
                        Placeholder::make('suggest_router_prompt_ai')
                            ->label('')
                            ->content(new HtmlString(
                                '<button type="button" wire:click="suggestRouterPromptWithAi" class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Suggest router prompt with AI</button>'
                            )),
                        Textarea::make('router_prompt')
                            ->rows(3)
                            ->placeholder('e.g. Given the user message, respond with JSON: intent, target_block_key, target_step_key, confidence (0-1), reason, customer_message, require_confirmation, variables.')
                            ->helperText('customer_message must be one short bot reply and must NOT repeat what the user said.'),
                        Textarea::make('system_prompt')
                            ->rows(2)
                            ->placeholder('e.g. You are a support chat router. Output only valid JSON.'),
                        TextInput::make('ai_model_override')->maxLength(255)->nullable(),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()->label('New step'),
            ])
            ->actions([
                EditAction::make()->label('Edit'),
                DeleteAction::make()->label('Delete'),
            ])
            ->columns([
                TextColumn::make('key'),
                TextColumn::make('bot_message_template')->limit(40),
                TextColumn::make('allowed_next_step_ids')
                    ->label('Allowed next steps')
                    ->formatStateUsing(function ($state, $record): string {
                        if (empty($state) || ! is_array($state)) {
                            return 'All steps';
                        }
                        $steps = \App\Models\Step::query()->whereIn('id', $state)->pluck('key')->all();
                        return implode(', ', $steps) ?: '—';
                    }),
                TextColumn::make('transition_rules')
                    ->label('Expected answers')
                    ->formatStateUsing(fn ($state): string => is_array($state) && count($state) > 0 ? count($state) . ' rule(s)' : '—'),
            ]);
    }

    /**
     * Call OpenRouter to suggest bot_message_template for this step; fill the form field.
     */
    public function suggestBotMessageWithAi(): void
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
        $stepKey = $state['key'] ?? 'this step';

        $systemPrompt = 'You suggest a short, friendly customer support bot message for a chat step. One or two sentences in English only. No JSON, no quotes—just the message text.';
        $userPrompt = sprintf('Step key: %s. Suggest the message the bot should show when the conversation reaches this step.', $stepKey);

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

        $this->form->fill(['bot_message_template' => $content]);
        Notification::make()
            ->title('Suggestion applied')
            ->body('Bot message filled. You can edit it before saving.')
            ->success()
            ->send();
    }

    /**
     * Call OpenRouter to suggest router_prompt for this step; fill the form field.
     */
    public function suggestRouterPromptWithAi(): void
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
        $stepKey = $state['key'] ?? 'this step';
        $allowedBlocks = $state['allowed_block_ids'] ?? [];
        $blockKeys = $allowedBlocks
            ? Block::query()->whereIn('id', $allowedBlocks)->pluck('key')->join(', ')
            : 'any block';
        $allowedSteps = $state['allowed_next_step_ids'] ?? [];
        $stepKeys = $allowedSteps
            ? \App\Models\Step::query()->whereIn('id', $allowedSteps)->pluck('key')->join(', ')
            : 'any step';

        $systemPrompt = 'You write instructions for an AI router. The router receives the user message and must output JSON with: intent, target_block_key, target_step_key, confidence (0-1), reason, customer_message (one short bot reply in English; must NOT repeat the user), require_confirmation, variables. Write clear instructions in English.';
        $userPrompt = sprintf(
            'Step key: %s. Allowed block keys (use only these for target_block_key): %s. Allowed step keys (use only these for target_step_key or null): %s. Write the router prompt instructions.',
            $stepKey,
            $blockKeys,
            $stepKeys
        );

        $response = $client->chat(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['max_tokens' => 400]
        );

        $content = trim($response['content'] ?? '');
        if ($content === '') {
            Notification::make()->title('No suggestion returned')->warning()->send();
            return;
        }

        $this->form->fill(['router_prompt' => $content]);
        Notification::make()
            ->title('Suggestion applied')
            ->body('Router prompt filled. You can edit it before saving.')
            ->success()
            ->send();
    }
}
