<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single suggestion (option) for a step. Defines action type, endpoint, next step, templates.
 * "What to send" = Endpoint.request_mapper + payload_mapper. "What we receive" = success/failure_template.
 */
class StepOption extends Model
{
    protected $fillable = [
        'step_id', 'label', 'bot_reply', 'action_type', 'endpoint_id',
        'payload_mapper', 'success_template', 'failure_template',
        'next_step_id', 'next_step_on_failure_id', 'confirm_step_id',
        'prompt_template', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'payload_mapper' => 'array',
        ];
    }

    /** Step this option belongs to. */
    public function step(): BelongsTo
    {
        return $this->belongsTo(Step::class);
    }

    /** Endpoint for API_CALL. */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }

    /** Step to transition to on success. */
    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(Step::class, 'next_step_id');
    }

    /** Step to transition to on failure. */
    public function nextStepOnFailure(): BelongsTo
    {
        return $this->belongsTo(Step::class, 'next_step_on_failure_id');
    }

    /** Step for confirmation (CONFIRM action). */
    public function confirmStep(): BelongsTo
    {
        return $this->belongsTo(Step::class, 'confirm_step_id');
    }

    /**
     * Human-readable summary of what this option triggers (for admin).
     */
    public function getTriggersSummaryAttribute(): string
    {
        switch ($this->action_type) {
            case 'API_CALL':
                return $this->endpoint_id
                    ? 'Endpoint: '.($this->endpoint?->name ?? '#'.$this->endpoint_id)
                    : '— No endpoint —';
            case 'NEXT_STEP':
                return $this->next_step_id
                    ? 'Step: '.($this->nextStep?->key ?? '#'.$this->next_step_id)
                    : '— No step —';
            case 'CONFIRM':
                return $this->confirm_step_id
                    ? 'Confirm step: '.($this->confirmStep?->key ?? '#'.$this->confirm_step_id)
                    : '— No step —';
            case 'HUMAN_HANDOFF':
                return 'Handoff to agent';
            case 'RUN_PROMPT':
                return $this->prompt_template
                    ? 'AI prompt: '.\Illuminate\Support\Str::limit($this->prompt_template, 30)
                    : '— No prompt —';
            case 'OPEN_URL':
            case 'NO_OP':
            default:
                return '—';
        }
    }
}
