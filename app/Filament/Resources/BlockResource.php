<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\BlockResource\RelationManagers\OptionsRelationManager;
use App\Models\Block;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlockResource extends Resource
{
    protected static ?string $model = Block::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Chat';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('key')->required()->maxLength(64)->unique(ignoreRecord: true),
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('message_template')->rows(3)->label('Message template'),
                Placeholder::make('suggest_message_ai')
                    ->label('')
                    ->content(new HtmlString(
                        '<button type="button" wire:click="suggestMessageWithAi" class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Suggest message with AI</button>'
                    )),
                KeyValue::make('display_rules'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) $state : 0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\BlockResource\Pages\ListBlocks::route('/'),
            'create' => \App\Filament\Resources\BlockResource\Pages\CreateBlock::route('/create'),
            'edit' => \App\Filament\Resources\BlockResource\Pages\EditBlock::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [OptionsRelationManager::class];
    }
}
