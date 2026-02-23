<?php

declare(strict_types=1);

namespace App\Services\Chat;

/**
 * Maps API response body to key-value variables using endpoint response_mapper config.
 * Config format: {"var_name": "json.path.to.field"} or {"var_name": ["path1", "path2"]} for first found.
 */
final class ResponseMapper
{
    /**
     * Extract variables from response body using response_mapper config.
     *
     * @param  array<string, string|array<int, string>>  $responseMapper  e.g. {"discount_code": "discount.code"}
     * @param  array<string, mixed>  $responseBody  Decoded JSON response
     * @return array<string, mixed>
     */
    public function map(array $responseMapper, array $responseBody): array
    {
        $out = [];
        foreach ($responseMapper as $varName => $path) {
            $value = $this->getByPath($responseBody, $path);
            $out[$varName] = $value;
        }

        return $out;
    }

    /**
     * Get value from array by path. Path can be dot-notation string or array of paths (first found wins).
     *
     * @param  array<string, mixed>  $data
     * @param  string|array<int, string>  $path
     */
    private function getByPath(array $data, string|array $path): mixed
    {
        $paths = is_array($path) ? $path : [$path];
        foreach ($paths as $p) {
            $value = $this->resolveDotPath($data, $p);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Resolve dot-notation path (e.g. "discount.code") in array.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveDotPath(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;
        foreach ($keys as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }
}
