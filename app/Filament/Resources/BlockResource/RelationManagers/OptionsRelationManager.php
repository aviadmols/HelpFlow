<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockResource\RelationManagers;

use App\Models\Step;
use App\Support\ChatConstants;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                TextInput::make('label')->required()->maxLength(255),
                Select::make('action_type')
                    ->label('Action type')
                    ->options(collect(ChatConstants::actionTypes())->mapWithKeys(fn ($v) => [$v => $v])->all())
                    ->required()
                    ->live()
                    ->helperText('Choose what runs when this option is clicked: Endpoint, Step, Confirm step, or Handoff.'),
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
                    ->visible(fn (Get $get) => in_array($get('action_type'), [ChatConstants::ACTION_TYPE_API_CALL, ChatConstants::ACTION_TYPE_NEXT_STEP], true))
                    ->helperText('For API_CALL: step after success. For NEXT_STEP: step to go to.'),
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
                TextInput::make('sort_order')->label('Sort order')->numeric()->default(0),
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
}
