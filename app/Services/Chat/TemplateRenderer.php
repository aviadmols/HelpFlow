<?php

declare(strict_types=1);

namespace App\Services\Chat;

/**
 * Renders message/success/failure templates with conversation context and variables. Safe defaults for missing vars.
 */
final class TemplateRenderer
{
    /**
     * Render a template string with given variables. Replaces {{key}} with value; unknown keys become empty string.
     *
     * @param  array<string, mixed>  $variables
     */
    public function render(string $template, array $variables = []): string
    {
        $result = $template;
        foreach ($variables as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            $result = str_replace($placeholder, (string) $value, $result);
        }
        // Remove any remaining {{...}} placeholders (safe default)
        $result = preg_replace('/\{\{[^}]+\}\}/', '', $result) ?? $result;

        return trim($result);
    }
}
