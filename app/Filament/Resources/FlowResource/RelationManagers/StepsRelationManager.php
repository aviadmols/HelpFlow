<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlowResource\RelationManagers;

use App\Models\Block;
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
                            ->placeholder('e.g. Given the user message, respond with JSON: intent, target_block_key (one of the allowed blocks), target_step_key, confidence (0-1), reason, customer_message, require_confirmation, variables.')
                            ->helperText('Prompt that defines how the AI interprets the user and chooses target block/step.'),
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
