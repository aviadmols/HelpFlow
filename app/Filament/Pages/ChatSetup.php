<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\BlockResource;
use App\Filament\Resources\EndpointResource;
use App\Filament\Resources\FlowResource;
use App\Filament\Resources\TicketResource;
use Filament\Pages\Page;

/**
 * Central page with quick links to manage Blocks, Flows, Endpoints, and Tickets.
 * Explains where to add Options (inside Block edit) and Steps (inside Flow edit).
 */
class ChatSetup extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.chat-setup';

    protected static ?string $navigationGroup = 'Chat';

    protected static ?string $title = 'Chat Setup';

    protected static ?string $navigationLabel = 'Chat Setup';

    public static function getRoutePath(): string
    {
        return 'chat-setup';
    }

    /**
     * URLs for quick links. Passed to the view.
     *
     * @return array<string, string>
     */
    public function getUrls(): array
    {
        return [
            'blocks_index' => BlockResource::getUrl('index'),
            'blocks_create' => BlockResource::getUrl('create'),
            'flows_index' => FlowResource::getUrl('index'),
            'flows_create' => FlowResource::getUrl('create'),
            'endpoints_index' => EndpointResource::getUrl('index'),
            'endpoints_create' => EndpointResource::getUrl('create'),
            'tickets_index' => TicketResource::getUrl('index'),
        ];
    }
}
