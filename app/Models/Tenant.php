<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Optional tenant for multi-tenancy. Scopes blocks, flows, conversations by tenant.
 */
class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /** Users belonging to this tenant. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Customers belonging to this tenant. */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** Flows belonging to this tenant. */
    public function flows(): HasMany
    {
        return $this->hasMany(Flow::class);
    }

    /** Blocks belonging to this tenant. */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    /** Endpoints belonging to this tenant. */
    public function endpoints(): HasMany
    {
        return $this->hasMany(Endpoint::class);
    }

    /** Conversations belonging to this tenant. */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
