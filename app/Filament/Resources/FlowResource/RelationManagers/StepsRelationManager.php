<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlowResource\RelationManagers;

use App\Models\Block;
use App\Models\Endpoint;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

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
                    ->collapsible(),
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
                    ->collapsible(),
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
            ->columns([
                TextColumn::make('key'),
                TextColumn::make('bot_message_template')->limit(40),
            ]);
    }
}
