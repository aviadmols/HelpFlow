<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockResource\RelationManagers;

use App\Models\Step;
use App\Support\ChatConstants;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('label')->required()->maxLength(255),
                Select::make('action_type')
                    ->options(collect(ChatConstants::actionTypes())->mapWithKeys(fn ($v) => [$v => $v])->all())
                    ->required(),
                Select::make('endpoint_id')
                    ->relationship('endpoint', 'name')
                    ->searchable()
                    ->nullable(),
                Textarea::make('success_template')->rows(2),
                Textarea::make('failure_template')->rows(2),
                Select::make('next_step_id')
                    ->options(function () {
                        $block = $this->getOwnerRecord();
                        $flowIds = $block->tenant?->flows()->pluck('id') ?? collect();

                        return Step::whereIn('flow_id', $flowIds)->orWhereHas('flow')->pluck('key', 'id')->all();
                    })
                    ->searchable()
                    ->nullable(),
                Select::make('next_step_on_failure_id')
                    ->options(fn () => Step::query()->pluck('key', 'id')->all())
                    ->searchable()
                    ->nullable(),
                Select::make('confirm_step_id')
                    ->options(fn () => Step::query()->pluck('key', 'id')->all())
                    ->searchable()
                    ->nullable(),
                TextInput::make('sort_order')->numeric()->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('action_type'),
                TextColumn::make('sort_order'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
