<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlowResource\RelationManagers;

use App\Models\Block;
use App\Models\Endpoint;
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
        return $form
            ->schema([
                TextInput::make('key')->required()->maxLength(64),
                Textarea::make('bot_message_template')->rows(3)->columnSpanFull(),
                Section::make('AI routing (this step)')
                    ->description('Override flow prompts for this step. Leave empty to use flow defaults.')
                    ->schema([
                        Textarea::make('router_prompt')
                            ->rows(3)
                            ->placeholder('e.g. Given the user message, respond with JSON: intent, target_block_key (one of the allowed blocks), target_step_key, confidence (0-1), reason, customer_message (one short bot reply; do NOT repeat or paraphrase the user), require_confirmation, variables.')
                            ->helperText('Prompt for the AI. customer_message must be one short bot reply and must NOT repeat what the user said. For a collect step (e.g. email + order number), ask for: intent, variables: { email, order_number }, target_step_key (next step key), confidence, reason, customer_message.'),
                        Textarea::make('system_prompt')
                            ->rows(2)
                            ->placeholder('e.g. You are a support chat router. Output only valid JSON.')
                            ->helperText('System instruction for the AI model for this step.'),
                    ])
                    ->columns(1)
                    ->collapsible(),
                Section::make('Allowed blocks & fallback')
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
                            ->helperText('For collect steps: after extracting email/order_number from the user message, call this endpoint and merge the response into context. Configure request_mapper with context.email, context.order_number.'),
                    ])
                    ->columns(1),
                TextInput::make('ai_model_override')->maxLength(255)->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                TextColumn::make('key'),
                TextColumn::make('bot_message_template')->limit(40),
            ]);
    }
}
