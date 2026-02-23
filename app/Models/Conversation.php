<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * Single chat conversation. Tracks flow, current step, context (encrypted), status.
 */
class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'customer_id', 'flow_id', 'current_step_id',
        'context', 'last_presented_block_key', 'status',
    ];

    /** Optional tenant. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Customer in this conversation. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Flow this conversation follows. */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    /** Current step in the flow. */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(Step::class, 'current_step_id');
    }

    /** Messages in this conversation. */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /** Action runs in this conversation. */
    public function actionRuns(): HasMany
    {
        return $this->hasMany(ActionRun::class);
    }

    /** AI telemetry records for this conversation. */
    public function aiTelemetry(): HasMany
    {
        return $this->hasMany(AiTelemetry::class, 'conversation_id');
    }

    /** Ticket if conversation was handed off. */
    public function ticket(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Get decrypted context as array. Returns [] if empty or invalid. */
    public function getContextArray(): array
    {
        if (empty($this->context)) {
            return [];
        }
        try {
            $decrypted = Crypt::decryptString($this->context);

            return json_decode($decrypted, true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Set and encrypt context from array. */
    public function setContextArray(array $data): void
    {
        $this->context = Crypt::encryptString(json_encode($data));
    }
}
