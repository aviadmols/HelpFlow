<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Single option (button) in a block. Defines action type, endpoint, next step, templates.
 */
class BlockOption extends Model
{
    protected $fillable = [
        'block_id', 'label', 'action_type', 'endpoint_id',
        'payload_mapper', 'success_template', 'failure_template',
        'next_step_id', 'next_step_on_failure_id', 'confirm_step_id',
        'retry_policy', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'payload_mapper' => 'array',
            'retry_policy' => 'array',
        ];
    }

    /** Block this option belongs to. */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
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

    /** Action runs for this option. */
    public function actionRuns(): HasMany
    {
        return $this->hasMany(ActionRun::class);
    }
}
