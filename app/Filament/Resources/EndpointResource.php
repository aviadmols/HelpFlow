<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\Endpoint;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EndpointResource extends Resource
{
    protected static ?string $model = Endpoint::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Chat';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('key')->required()->maxLength(64),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('method')->options(['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH'])->default('POST'),
                TextInput::make('url')->required()->url()->maxLength(512),
                TextInput::make('timeout_sec')->numeric()->default(30),
                TextInput::make('retries')->numeric()->default(0),
                KeyValue::make('request_mapper')->keyLabel('Variable')->valueLabel('Source (e.g. context.field)'),
                KeyValue::make('response_mapper')->keyLabel('Variable')->valueLabel('JSON path (e.g. discount.code)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('method'),
                TextColumn::make('url')->limit(40),
            ])
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\EndpointResource\Pages\ListEndpoints::route('/'),
            'create' => \App\Filament\Resources\EndpointResource\Pages\CreateEndpoint::route('/create'),
            'edit' => \App\Filament\Resources\EndpointResource\Pages\EditEndpoint::route('/{record}/edit'),
        ];
    }
}
