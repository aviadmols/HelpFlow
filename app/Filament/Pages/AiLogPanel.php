<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AiTelemetry;
use Filament\Pages\Page;

/**
 * Admin page showing recent AI routing decisions (telemetry) in a table.
 */
class AiLogPanel extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string $view = 'filament.pages.ai-log-panel';

    protected static ?string $navigationGroup = 'Chat';

    protected static ?string $title = 'AI Log';

    public static function getNavigationLabel(): string
    {
        return 'AI Log';
    }

    public function getTelemetry(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return AiTelemetry::query()
            ->with('conversation', 'message')
            ->latest()
            ->paginate(20);
    }
}
