<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key-value settings store for admin-configurable values (e.g. OpenRouter API key).
 * Values are cached to reduce DB reads.
 */
class Setting extends Model
{
    public $timestamps = true;

    protected $fillable = ['key', 'value'];

    /**
     * Cache key prefix for settings.
     */
    public const CACHE_PREFIX = 'helpflow.setting.';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL = 3600;

    /** Sentinel stored in cache when DB value is null. */
    private const CACHE_NULL = '__NULL__';

    /**
     * Get a setting value by key. Returns null if not set. Uses cache.
     */
    public static function get(string $key): ?string
    {
        $cacheKey = self::CACHE_PREFIX.$key;
        $cached = Cache::get($cacheKey);
        if (Cache::has($cacheKey)) {
            return $cached === self::CACHE_NULL ? null : $cached;
        }
        $record = self::query()->where('key', $key)->first();
        $val = $record?->value;
        Cache::put($cacheKey, $val === null ? self::CACHE_NULL : $val, self::CACHE_TTL);

        return $val;
    }

    /**
     * Set a setting value. Clears cache for the key.
     */
    public static function set(string $key, ?string $value): void
    {
        self::query()->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
        Cache::forget(self::CACHE_PREFIX.$key);
    }
}
