<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Option block. Contains message template and options (buttons).
 */
class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'key', 'title', 'message_template', 'display_rules', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['display_rules' => 'array'];
    }

    /** Optional tenant. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Options (buttons) for this block. */
    public function options(): HasMany
    {
        return $this->hasMany(BlockOption::class)->orderBy('sort_order');
    }
}
