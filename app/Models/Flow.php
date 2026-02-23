<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Flow definition. Contains steps and AI router config (prompts, default model).
 */
class Flow extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'key', 'name', 'active',
        'default_model', 'router_prompt', 'system_prompt',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** Optional tenant. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Steps in this flow (ordered by sort_order then id). */
    public function steps(): HasMany
    {
        return $this->hasMany(Step::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Conversations using this flow. */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
