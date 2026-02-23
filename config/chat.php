<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache TTL for block/flow config (seconds)
    |--------------------------------------------------------------------------
    */
    'cache_ttl_seconds' => (int) env('CHAT_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Default flow key when none specified on conversation start
    |--------------------------------------------------------------------------
    */
    'default_flow_key' => env('CHAT_DEFAULT_FLOW_KEY', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Fallback block key when AI router fails or low confidence
    |--------------------------------------------------------------------------
    */
    'fallback_block_key' => env('CHAT_FALLBACK_BLOCK_KEY', 'main_menu'),

];
