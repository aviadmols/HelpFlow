<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Conversation;
use Filament\Pages\Page;

class ConversationViewer extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.conversation-viewer';

    protected static bool $shouldRegisterNavigation = false;

    public ?Conversation $conversation = null;

    public function mount(int $conversation): void
    {
        $this->conversation = Conversation::with([
            'messages',
            'actionRuns.endpoint',
            'actionRuns.blockOption',
            'aiTelemetry',
            'customer',
            'flow',
        ])->findOrFail($conversation);
    }

    public static function getRoutePath(): string
    {
        return 'conversations/{conversation}';
    }

    public function getTitle(): string
    {
        return 'Conversation #'.($this->conversation?->id ?? '');
    }
}
