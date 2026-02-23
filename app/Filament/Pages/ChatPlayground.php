<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Flow;
use Filament\Pages\Page;

class ChatPlayground extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string $view = 'filament.pages.chat-playground';

    protected static ?string $navigationGroup = 'Chat';

    protected static ?string $title = 'Chat Playground';

    /** @var array<int, array{id: int, key: string, name: string}> */
    public array $flows = [];

    public function mount(): void
    {
        $this->flows = Flow::where('active', true)
            ->orderBy('name')
            ->get(['id', 'key', 'name'])
            ->map(fn (Flow $f) => ['id' => $f->id, 'key' => $f->key, 'name' => $f->name])
            ->values()
            ->all();
    }

    public static function getRoutePath(): string
    {
        return 'chat-playground';
    }
}
