<?php

namespace App\Filament\Resources\FlowResource\Pages;

use App\Filament\Resources\FlowResource;
use Filament\Resources\Pages\EditRecord;

class EditFlow extends EditRecord
{
    protected static string $resource = FlowResource::class;

    /** Show Flow form and Steps as tabs at the top so Steps are visible. */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Flow';
    }
}
