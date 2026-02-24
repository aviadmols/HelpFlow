<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FlowResource\RelationManagers\StepsRelationManager;
use App\Models\Flow;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FlowResource extends Resource
{
    protected static ?string $model = Flow::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Chat';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('key')->required()->maxLength(64),
                TextInput::make('name')->required()->maxLength(255),
                Toggle::make('active')->default(true),
                TextInput::make('default_model')->maxLength(255)->placeholder('e.g. openai/gpt-4o-mini'),
                Textarea::make('router_prompt')->rows(4)->placeholder('Instructions for AI router (JSON output).'),
                Textarea::make('system_prompt')->rows(3)->placeholder('System prompt for the router model.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                IconColumn::make('active')->boolean(),
            ])
            ->actions([\Filament\Tables\Actions\EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [StepsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\FlowResource\Pages\ListFlows::route('/'),
            'create' => \App\Filament\Resources\FlowResource\Pages\CreateFlow::route('/create'),
            'edit' => \App\Filament\Resources\FlowResource\Pages\EditFlow::route('/{record}/edit'),
            'step-review' => \App\Filament\Resources\FlowResource\Pages\StepReviewPage::route('/{record}/step-review'),
        ];
    }
}
