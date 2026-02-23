<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * Configurable HTTP endpoint for API_CALL actions. Stores URL, auth, mappers; sensitive fields encrypted.
 */
class Endpoint extends Model
{
    protected $fillable = [
        'tenant_id', 'key', 'name', 'method', 'url',
        'headers', 'auth_type', 'auth_config',
        'timeout_sec', 'retries', 'request_mapper', 'response_mapper',
    ];

    protected function casts(): array
    {
        return [
            'request_mapper' => 'array',
            'response_mapper' => 'array',
        ];
    }

    /** Optional tenant. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Block options that use this endpoint. */
    public function blockOptions(): HasMany
    {
        return $this->hasMany(BlockOption::class, 'endpoint_id');
    }

    /** Action runs that used this endpoint. */
    public function actionRuns(): HasMany
    {
        return $this->hasMany(ActionRun::class);
    }

    /** Decrypt and return headers array. Returns [] if not set. */
    public function getDecryptedHeaders(): array
    {
        if (empty($this->headers)) {
            return [];
        }
        try {
            $decrypted = Crypt::decryptString($this->headers);

            return json_decode($decrypted, true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Encrypt and set headers. */
    public function setEncryptedHeaders(array $headers): void
    {
        $this->headers = Crypt::encryptString(json_encode($headers));
    }

    /** Decrypt and return auth_config. Returns [] if not set. */
    public function getDecryptedAuthConfig(): array
    {
        if (empty($this->auth_config)) {
            return [];
        }
        try {
            $decrypted = Crypt::decryptString($this->auth_config);

            return json_decode($decrypted, true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Encrypt and set auth_config. */
    public function setEncryptedAuthConfig(array $config): void
    {
        $this->auth_config = Crypt::encryptString(json_encode($config));
    }
}
