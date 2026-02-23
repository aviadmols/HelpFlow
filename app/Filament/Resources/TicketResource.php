<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\Ticket;
use App\Support\ChatConstants;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Chat';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('assigned_to')->relationship('assignedTo', 'name')->searchable()->nullable(),
                Select::make('status')->options([
                    ChatConstants::TICKET_STATUS_OPEN => 'Open',
                    ChatConstants::TICKET_STATUS_IN_PROGRESS => 'In Progress',
                    ChatConstants::TICKET_STATUS_RESOLVED => 'Resolved',
                ])->required(),
                Textarea::make('internal_notes')->rows(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('conversation.id')->label('Conversation'),
                TextColumn::make('assignedTo.name')->label('Assigned to'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')->options([
                    ChatConstants::TICKET_STATUS_OPEN => 'Open',
                    ChatConstants::TICKET_STATUS_IN_PROGRESS => 'In Progress',
                    ChatConstants::TICKET_STATUS_RESOLVED => 'Resolved',
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\TicketResource\Pages\ListTickets::route('/'),
            'edit' => \App\Filament\Resources\TicketResource\Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}
