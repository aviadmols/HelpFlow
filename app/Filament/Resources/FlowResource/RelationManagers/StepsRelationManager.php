<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlowResource\RelationManagers;

use App\Models\Block;
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
        $flowId = $this->getOwnerRecord()->id;

        return $form
            ->schema([
                TextInput::make('key')->required()->maxLength(64),
                Textarea::make('bot_message_template')->rows(3),
                Select::make('fallback_block_id')
                    ->options(Block::query()->pluck('title', 'id')->all())
                    ->searchable()
                    ->nullable(),
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
