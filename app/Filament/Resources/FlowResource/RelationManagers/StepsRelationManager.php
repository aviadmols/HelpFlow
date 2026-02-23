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
use Filament\Infolists\Infolist;
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

    public function getInfolist(string $name): ?Infolist
    {
        if ($name === 'form') {
            return null;
        }

        return parent::getInfolist($name);
    }

    /**
     * @return array<string, string>
     */
    protected static function getExamplePrompts(): array
    {
        return [
            'customer_service' => 'Act as a friendly customer service agent. Politely ask for the customer\'s name, then their email or phone. Then ask how you can help and, based on their answer, direct them to subscription management, cancellation, or order update. If nothing fits, tell them a representative will contact them shortly.',
            'order_lookup' => 'Take the customer email from global context and send a POST request to endpoint XXXX. It will return the list of orders. After receiving the orders, ask the customer which order they want to cancel.',
        ];
    }

    public function fillExamplePrompt(string $key): void
    {
        $prompts = static::getExamplePrompts();
        if (! isset($prompts[$key])) {
            return;
        }
        $form = $this->getMountedTableActionForm() ?? $this->form();
        $state = $form->getState();
        $state['ai_step_description'] = $prompts[$key];
        $form->fill($state);
    }

    public function form(?Form $form = null): Form
    {
        $form ??= $this->makeForm();
        $flow = $this->getOwnerRecord();
        $stepOptions = $flow->steps()->pluck('key', 'id')->all();
        $stepKeyOptions = $flow->steps()->pluck('key', 'key')->all();
        $blockKeyOptions = Block::query()->get()->mapWithKeys(fn ($b) => [$b->key => $b->title ?? $b->key])->all();

        return $form
            ->schema([
                Section::make('AI module')
                    ->icon('heroicon-m-information-circle')
                    ->description('Describe what you want this step to do; click "Generate step" to fill the fields from AI.')
                    ->schema([
                        Textarea::make('ai_step_description')
                            ->label('What should this step do?')
                            ->placeholder('e.g. Collect customer email and order number, then ask what they want to do: track order, cancel, or get help.')
                            ->rows(4)
                            ->columnSpanFull()
                            ->live()
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Describe in one sentence what this step should do. Click "Generate step" to auto-fill the form from this description using AI.'),
                        Placeholder::make('generate_step_ai_btn')
                            ->label('')
                            ->content(new HtmlString(
                                '<button type="button" wire:click="generateStepWithAi" class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Generate step</button>'
                            )),
                        Placeholder::make('example_prompts_btns')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="flex flex-wrap gap-2 mt-2">'.
                                '<span class="text-sm text-gray-500 dark:text-gray-400">Examples:</span>'.
                                '<button type="button" wire:click="fillExamplePrompt(\'customer_service\')" class="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-2 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Example: Customer service</button>'.
                                '<button type="button" wire:click="fillExamplePrompt(\'order_lookup\')" class="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-2 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Example: Order lookup and cancel</button>'.
                                '</div>'
                            )),
                    ])
                    ->collapsible()
                    ->collapsed(false)
                    ->columnSpanFull(),
                TextInput::make('key')
                    ->required()
                    ->maxLength(64)
                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Unique identifier for this step (e.g. welcome, collect_order, confirm_cancel). Use snake_case. Used in transitions and URLs.'),
                Textarea::make('bot_message_template')
                    ->label('Bot message to customer')
                    ->rows(3)
                    ->columnSpanFull()
                    ->hintIcon('heroicon-m-information-circle', tooltip: 'The message the bot shows when the conversation reaches this step. You can use variables like {{ context.email }}. Use "Suggest bot message with AI" to generate a draft.'),
                Placeholder::make('suggest_bot_message_ai')
                    ->label('')
                    ->content(new HtmlString(
                        '<button type="button" wire:click="suggestBotMessageWithAi" class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Suggest bot message with AI</button>'
                    )),
                Section::make('Session variables (saved to conversation context)')
                    ->icon('heroicon-m-information-circle')
                    ->description('Define which values this step will save as global session variables. The AI router will extract them from the user message; they are stored in the conversation context and available in later steps in message templates as {{ key }} (e.g. {{ email }}, {{ order_number }}) and in payload mapping as context.key.')
                    ->schema([
                        Repeater::make('context_variables')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Add variable keys this step should collect and save. Use snake_case (e.g. email, order_number, customer_name). In later steps and in templates you reference them as context.email, context.order_number.')
                            ->schema([
                                TextInput::make('key')
                                    ->label('Context key')
                                    ->placeholder('e.g. email, order_number, customer_name')
                                    ->required()
                                    ->maxLength(64)
                                    ->helperText('Use snake_case. This step will save the extracted value under context.<key>.')
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Name of the variable in conversation context. Use only letters, numbers, underscore.'),
                                TextInput::make('label')
                                    ->label('Label (optional)')
                                    ->placeholder('e.g. Customer email')
                                    ->maxLength(255)
                                    ->nullable()
                                    ->helperText('Human-readable description for the AI (what to extract).')
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Optional description shown to the AI so it knows what to extract from the user message.'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['key'] ?? null)
                            ->reorderable(),
                    ])
                    ->collapsible()
                    ->collapsed(false),
                Section::make('Next steps')
                    ->icon('heroicon-m-information-circle')
                    ->description('Which steps the conversation can move to from this step. Leave empty to allow any step in the flow.')
                    ->schema([
                        Select::make('allowed_next_step_ids')
                            ->label('Allowed next steps')
                            ->options($stepOptions)
                            ->multiple()
                            ->searchable()
                            ->helperText('Only these steps can be reached from this step. Empty = all flow steps allowed.')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Restrict which steps the user can go to from this step. Leave empty to allow any step in the flow. The AI router will only suggest these steps.'),
                    ])
                    ->collapsible()
                    ->collapsed(false),
                Section::make('Expected answers (transition rules)')
                    ->icon('heroicon-m-information-circle')
                    ->description('Define intents or keywords that map to a target step or block. Used to guide the AI or for direct matching before calling the AI.')
                    ->schema([
                        Repeater::make('transition_rules')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Add rules: when the user says something matching "Intent / keywords", the conversation goes to the chosen step or block. Use for quick matches (e.g. "cancel" → cancel step) before or instead of calling the AI.')
                            ->schema([
                                TextInput::make('intent')
                                    ->label('Intent / keywords')
                                    ->placeholder('e.g. cancel, update order')
                                    ->required()
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Keywords or intent name. When the user message matches, the conversation goes to the target step or block. Used for direct matching and to guide the AI.'),
                                Select::make('target_step_key')
                                    ->label('Target step')
                                    ->options($stepKeyOptions)
                                    ->nullable()
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Step to jump to when this intent is matched. Leave empty if you only use target block.'),
                                Select::make('target_block_key')
                                    ->label('Target block')
                                    ->options($blockKeyOptions)
                                    ->nullable()
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Block to show when this intent is matched (e.g. FAQ block). Can be used with or without a target step.'),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['intent'] ?? null),
                    ])
                    ->collapsible()
                    ->collapsed(false),
                Section::make('Suggestions (options for this step)')
                    ->icon('heroicon-m-information-circle')
                    ->description('Buttons shown to the customer in this step. When set, these replace block-based options. What to send: map context to request body. What we receive: use success/failure template with variables from the endpoint response mapping.')
                    ->schema([
                        Repeater::make('stepOptions')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Define buttons the customer sees in this step (e.g. "Track order", "Cancel"). Each option can call an API, go to a step, show a confirmation, or run AI. Only visible when editing an existing step.')
                            ->relationship(
                                modifyQueryUsing: fn ($query) => $query->orderBy('sort_order')->orderBy('id')
                            )
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(255)
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Button label shown to the customer (e.g. "Track my order", "Cancel order").'),
                                TextInput::make('bot_reply')
                                    ->label('Bot reply (optional)')
                                    ->maxLength(512)
                                    ->nullable()
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Short message the bot sends when the customer clicks this option. Optional.'),
                                Select::make('action_type')
                                    ->label('Action type')
                                    ->options(collect(ChatConstants::actionTypes())->mapWithKeys(fn ($v) => [$v => $v])->all())
                                    ->required()
                                    ->live()
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'What happens when the customer clicks: API_CALL (call endpoint), NEXT_STEP (go to step), CONFIRM (confirmation step), HUMAN_HANDOFF, OPEN_URL, NO_OP, RUN_PROMPT (AI).'),
                                Select::make('endpoint_id')
                                    ->label('Endpoint')
                                    ->relationship('endpoint', 'name')
                                    ->searchable()
                                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL)
                                    ->nullable()
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL)
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'The API endpoint to call when this option is selected. Configure endpoints in the Endpoints resource.'),
                                KeyValue::make('payload_mapper')
                                    ->label('What to send (payload mapping)')
                                    ->keyLabel('Request key')
                                    ->valueLabel('Context source (e.g. context.email)')
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL)
                                    ->helperText('Map context/customer to request body. Endpoint request_mapper is applied first.')
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Map each request key to a context variable (e.g. context.email, context.order_number). These are sent in the API request body.'),
                                Textarea::make('success_template')
                                    ->label('Success template (what we receive)')
                                    ->rows(2)
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL)
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Message shown when the API returns success. Use variables from the endpoint response mapping (e.g. {{ response.order_status }}).'),
                                Textarea::make('failure_template')
                                    ->label('Failure template')
                                    ->rows(2)
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL)
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Message shown when the API call fails. You can use variables from context or a fixed message.'),
                                Select::make('next_step_id')
                                    ->label('Next step')
                                    ->options($stepOptions)
                                    ->searchable()
                                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_NEXT_STEP)
                                    ->nullable()
                                    ->visible(fn (Get $get) => in_array($get('action_type'), [ChatConstants::ACTION_TYPE_API_CALL, ChatConstants::ACTION_TYPE_NEXT_STEP, ChatConstants::ACTION_TYPE_RUN_PROMPT], true))
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Step to go to after this action (e.g. after API success or for NEXT_STEP).'),
                                Select::make('next_step_on_failure_id')
                                    ->label('Next step on failure')
                                    ->options($stepOptions)
                                    ->searchable()
                                    ->nullable()
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_API_CALL)
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Step to go to when the API call fails. Optional.'),
                                Select::make('confirm_step_id')
                                    ->label('Confirmation step')
                                    ->options($stepOptions)
                                    ->searchable()
                                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_CONFIRM)
                                    ->nullable()
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_CONFIRM)
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Step that asks the user to confirm (e.g. "Are you sure you want to cancel?"). Required for CONFIRM action type.'),
                                Textarea::make('prompt_template')
                                    ->label('AI prompt (RUN_PROMPT)')
                                    ->rows(3)
                                    ->required(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_RUN_PROMPT)
                                    ->nullable()
                                    ->visible(fn (Get $get) => $get('action_type') === ChatConstants::ACTION_TYPE_RUN_PROMPT)
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Prompt sent to the AI when the customer clicks this option. Use for free-form AI replies before going to next step.'),
                                TextInput::make('sort_order')
                                    ->label('Sort order')
                                    ->numeric()
                                    ->default(0)
                                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Order of this button among the step options. Lower numbers appear first.'),
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
                    ->icon('heroicon-m-information-circle')
                    ->description('Blocks the customer can be directed to in this step. If the AI does not recognize the intent, the fallback block is shown.')
                    ->schema([
                        Select::make('allowed_block_ids')
                            ->label('Allowed blocks')
                            ->options(Block::query()->pluck('title', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->helperText('Only these blocks can be shown in this step. Leave empty to allow any block.')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Restrict which content blocks the AI can show in this step. Empty = any block. Blocks are reusable content (e.g. FAQ, policies).'),
                        Select::make('fallback_block_id')
                            ->label('Fallback block (when AI doesn\'t recognize)')
                            ->options(Block::query()->pluck('title', 'id')->all())
                            ->searchable()
                            ->nullable()
                            ->helperText('Block to show when confidence is low or intent is unclear.')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'When the AI is unsure or the intent is unclear, this block is shown (e.g. "Sorry, I didn\'t understand. Here are some options.").'),
                        Select::make('order_lookup_endpoint_id')
                            ->label('Order lookup endpoint (optional)')
                            ->options(Endpoint::query()->pluck('name', 'id')->all())
                            ->searchable()
                            ->nullable()
                            ->helperText('For collect steps: after extracting email/order_number from the user message, call this endpoint and merge the response into context.')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Optional. After the user provides email/order number, call this endpoint and add the response to context for the rest of the conversation.'),
                    ])
                    ->columns(1),
                Section::make('AI routing (this step)')
                    ->icon('heroicon-m-information-circle')
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
                            ->helperText('customer_message must be one short bot reply and must NOT repeat what the user said.')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Instructions for the AI router in this step. Define the expected JSON shape and how to choose target step/block. Overrides the flow-level router prompt.'),
                        Textarea::make('system_prompt')
                            ->rows(2)
                            ->placeholder('e.g. You are a support chat router. Output only valid JSON.')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'System prompt for the router AI (e.g. role and output format). Leave empty to use the flow default.'),
                        TextInput::make('ai_model_override')
                            ->maxLength(255)
                            ->nullable()
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Override the flow AI model for this step only (e.g. openai/gpt-4). Leave empty to use the flow default.'),
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

        $form = $this->getMountedTableActionForm() ?? $this->form();
        $state = $form->getState();
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

        $form->fill(['bot_message_template' => $content]);
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

        $form = $this->getMountedTableActionForm() ?? $this->form();
        $state = $form->getState();
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

        $form->fill(['router_prompt' => $content]);
        Notification::make()
            ->title('Suggestion applied')
            ->body('Router prompt filled. You can edit it before saving.')
            ->success()
            ->send();
    }

    /**
     * Generate step configuration from a free-text description using OpenRouter; fill the form.
     */
    public function generateStepWithAi(): void
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

        $form = $this->getMountedTableActionForm() ?? $this->form();
        $state = $form->getState();
        $description = trim((string) ($state['ai_step_description'] ?? ''));
        if ($description === '') {
            Notification::make()
                ->title('Empty description')
                ->body('Describe what you want this step to do.')
                ->warning()
                ->send();
            return;
        }

        $flow = $this->getOwnerRecord();
        $steps = $flow->steps()->orderBy('sort_order')->orderBy('id')->get();
        $stepKeyToId = $steps->pluck('id', 'key')->all();
        $stepKeysList = $steps->pluck('key')->implode(', ');
        $blocks = Block::query()->get();
        $blockKeyToId = $blocks->pluck('id', 'key')->all();
        $blockKeysList = $blocks->map(fn ($b) => $b->key . ' (' . ($b->title ?? $b->key) . ')')->implode(', ');
        $endpoints = Endpoint::query()->get();
        $endpointNameToId = $endpoints->pluck('id', 'name')->all();
        $endpointNamesList = $endpoints->pluck('name')->implode(', ');

        $currentStepKey = $this->getMountedTableActionRecord()?->key;

        $systemPrompt = <<<PROMPT
You are a step designer for a customer support chat flow. Given a short description of what the step should do, output ONLY valid JSON (no markdown, no code fence). Use this exact structure:

{
  "key": "snake_case_step_key",
  "bot_message_template": "One or two sentences the bot shows to the customer in English.",
  "router_prompt": "Optional instructions for the AI router (or null).",
  "context_variables": [
    { "key": "variable_key_snake_case", "label": "Optional human-readable description" }
  ],
  "transition_rules": [
    { "intent": "keywords or intent", "target_step_key": "step_key or null", "target_block_key": "block_key or null" }
  ],
  "step_options": [
    {
      "label": "Button label",
      "bot_reply": "Optional one-line reply when clicked",
      "action_type": "API_CALL | NEXT_STEP | CONFIRM | HUMAN_HANDOFF | OPEN_URL | NO_OP | RUN_PROMPT",
      "endpoint_name": "Only for API_CALL - use exact endpoint name from context",
      "next_step_key": "For NEXT_STEP or API_CALL success - step key from context",
      "next_step_on_failure_key": "For API_CALL - step key on failure",
      "confirm_step_key": "Only for CONFIRM - step key for confirmation",
      "success_template": "For API_CALL - optional",
      "failure_template": "For API_CALL - optional",
      "prompt_template": "For RUN_PROMPT only",
      "payload_mapper": {},
      "sort_order": 0
    }
  ]
}

Rules: Use only step keys, block keys, and endpoint names from the context provided. action_type must be one of: API_CALL, NEXT_STEP, CONFIRM, HUMAN_HANDOFF, OPEN_URL, NO_OP, RUN_PROMPT. For context_variables use snake_case keys (e.g. email, order_number, customer_name); these are saved to the conversation and available in later steps as context.key. Omit step_options if the step should have no suggestion buttons. Keep transition_rules, context_variables and step_options empty arrays if not needed.
PROMPT;

        $userPrompt = sprintf(
            "Flow name: %s. Existing step keys in this flow: %s. Block keys (key (title)): %s. Endpoint names: %s. Current step key (if editing): %s.\n\nUser description: %s",
            $flow->name ?? 'Flow',
            $stepKeysList ?: '(none yet)',
            $blockKeysList ?: '(none)',
            $endpointNamesList ?: '(none)',
            $currentStepKey ?? '(new step)',
            $description
        );

        $response = $client->chat(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['max_tokens' => 1200]
        );

        $content = trim((string) ($response['content'] ?? ''));
        if ($content === '') {
            Notification::make()->title('No response from AI')->warning()->send();
            return;
        }

        $json = $this->extractJsonFromAiResponse($content);
        if ($json === null) {
            Notification::make()
                ->title('Invalid JSON')
                ->body('Could not parse AI response. Try a shorter description.')
                ->danger()
                ->send();
            return;
        }

        $fill = $this->mapAiStepToFormData($json, $stepKeyToId, $blockKeyToId, $endpointNameToId);
        $isExistingStep = (bool) $this->getMountedTableActionRecord()?->getKey();

        if (! $isExistingStep && isset($fill['stepOptions'])) {
            unset($fill['stepOptions']);
        }

        $form->fill($fill);
        Notification::make()
            ->title('Step generated')
            ->body('Fields have been filled. Edit and save as needed.')
            ->success()
            ->send();
    }

    private function extractJsonFromAiResponse(string $content): ?array
    {
        $content = trim($content);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $m)) {
            $content = trim($m[1]);
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, int>  $stepKeyToId
     * @param  array<string, int>  $blockKeyToId
     * @param  array<string, int>  $endpointNameToId
     * @return array<string, mixed>
     */
    private function mapAiStepToFormData(array $json, array $stepKeyToId, array $blockKeyToId, array $endpointNameToId): array
    {
        $fill = [];
        if (isset($json['key']) && is_string($json['key'])) {
            $fill['key'] = $json['key'];
        }
        if (isset($json['bot_message_template']) && is_string($json['bot_message_template'])) {
            $fill['bot_message_template'] = $json['bot_message_template'];
        }
        if (array_key_exists('router_prompt', $json)) {
            $fill['router_prompt'] = $json['router_prompt'] === null ? '' : (string) $json['router_prompt'];
        }

        if (isset($json['context_variables']) && is_array($json['context_variables'])) {
            $fill['context_variables'] = [];
            foreach ($json['context_variables'] as $item) {
                if (! is_array($item) || empty($item['key'])) {
                    continue;
                }
                $fill['context_variables'][] = [
                    'key' => $item['key'],
                    'label' => $item['label'] ?? null,
                ];
            }
        }

        $allowedNextStepIds = [];
        if (isset($json['transition_rules']) && is_array($json['transition_rules'])) {
            $fill['transition_rules'] = [];
            foreach ($json['transition_rules'] as $rule) {
                if (! is_array($rule)) {
                    continue;
                }
                $intent = $rule['intent'] ?? '';
                $targetStepKey = $rule['target_step_key'] ?? null;
                $targetBlockKey = $rule['target_block_key'] ?? null;
                if ($targetStepKey !== null && isset($stepKeyToId[$targetStepKey])) {
                    $allowedNextStepIds[$stepKeyToId[$targetStepKey]] = true;
                }
                $fill['transition_rules'][] = [
                    'intent' => $intent,
                    'target_step_key' => $targetStepKey,
                    'target_block_key' => $targetBlockKey,
                ];
            }
        }

        if (isset($json['step_options']) && is_array($json['step_options'])) {
            $fill['stepOptions'] = [];
            foreach ($json['step_options'] as $i => $opt) {
                if (! is_array($opt)) {
                    continue;
                }
                $mapped = [
                    'label' => $opt['label'] ?? 'Option',
                    'bot_reply' => $opt['bot_reply'] ?? null,
                    'action_type' => $opt['action_type'] ?? ChatConstants::ACTION_TYPE_NO_OP,
                    'endpoint_id' => null,
                    'payload_mapper' => $opt['payload_mapper'] ?? null,
                    'success_template' => $opt['success_template'] ?? null,
                    'failure_template' => $opt['failure_template'] ?? null,
                    'next_step_id' => null,
                    'next_step_on_failure_id' => null,
                    'confirm_step_id' => null,
                    'prompt_template' => $opt['prompt_template'] ?? null,
                    'sort_order' => (int) ($opt['sort_order'] ?? $i),
                ];
                $endpointName = $opt['endpoint_name'] ?? null;
                if (is_string($endpointName) && isset($endpointNameToId[$endpointName])) {
                    $mapped['endpoint_id'] = $endpointNameToId[$endpointName];
                }
                $nextKey = $opt['next_step_key'] ?? null;
                if (is_string($nextKey) && isset($stepKeyToId[$nextKey])) {
                    $mapped['next_step_id'] = $stepKeyToId[$nextKey];
                    $allowedNextStepIds[$stepKeyToId[$nextKey]] = true;
                }
                $failureKey = $opt['next_step_on_failure_key'] ?? null;
                if (is_string($failureKey) && isset($stepKeyToId[$failureKey])) {
                    $mapped['next_step_on_failure_id'] = $stepKeyToId[$failureKey];
                }
                $confirmKey = $opt['confirm_step_key'] ?? null;
                if (is_string($confirmKey) && isset($stepKeyToId[$confirmKey])) {
                    $mapped['confirm_step_id'] = $stepKeyToId[$confirmKey];
                }
                $fill['stepOptions'][] = $mapped;
            }
        }

        if ($allowedNextStepIds !== []) {
            $fill['allowed_next_step_ids'] = array_keys($allowedNextStepIds);
        }

        return $fill;
    }
}
