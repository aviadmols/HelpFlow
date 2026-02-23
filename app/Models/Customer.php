<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer entity. Links to conversations; may have external_id for 3rd party systems.
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'external_id', 'email', 'name', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /** Optional tenant. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Conversations of this customer. */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
