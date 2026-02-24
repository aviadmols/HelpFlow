<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlowResource\Pages;

use App\Filament\Resources\FlowResource;
use App\Models\Step;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Contracts\Support\Htmlable;

class StepReviewPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FlowResource::class;

    protected static string $view = 'filament.resources.flow-resource.pages.step-review-page';

    public ?Step $step = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $stepId = request()->query('step_id');
        $stepKey = request()->query('step_key');
        if (! $stepId && ! $stepKey) {
            $this->redirect(FlowResource::getUrl('edit', ['record' => $this->record]));

            return;
        }
        $flow = $this->getRecord();
        $query = $flow->steps();
        if ($stepId) {
            $query->where('id', (int) $stepId);
        } else {
            $query->where('key', $stepKey);
        }
        $this->step = $query->first();
        if (! $this->step) {
            $this->redirect(FlowResource::getUrl('edit', ['record' => $this->record]));

            return;
        }
    }

    public function getTitle(): string|Htmlable
    {
        $stepKey = $this->step?->key ?? 'step';

        return 'Review step: ' . $stepKey;
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    /**
     * Flow key for the chat API.
     */
    public function getFlowKey(): string
    {
        return (string) $this->getRecord()->key;
    }

    /**
     * Step key for the chat API (start at this step).
     */
    public function getStepKey(): string
    {
        return (string) ($this->step?->key ?? '');
    }

    /**
     * Flow name for display.
     */
    public function getFlowName(): string
    {
        return (string) ($this->getRecord()->name ?? '');
    }
}
