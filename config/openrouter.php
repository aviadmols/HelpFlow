<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenRouter API base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | API Key (store in .env; can be overridden per flow/step in DB)
    |--------------------------------------------------------------------------
    */
    'api_key' => env('OPENROUTER_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Default model when not overridden by flow/step
    |--------------------------------------------------------------------------
    */
    'default_model' => env('OPENROUTER_DEFAULT_MODEL', 'openai/gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Default request options
    |--------------------------------------------------------------------------
    */
    'temperature' => (float) env('OPENROUTER_TEMPERATURE', 0.3),
    'top_p' => (float) env('OPENROUTER_TOP_P', 1.0),
    'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 1024),

    /*
    |--------------------------------------------------------------------------
    | Timeout for HTTP request (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout_sec' => (int) env('OPENROUTER_TIMEOUT', 30),

];
