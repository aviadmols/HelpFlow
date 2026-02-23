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

    /*
    |--------------------------------------------------------------------------
    | Default AI router prompts (used when flow/step have none)
    |--------------------------------------------------------------------------
    | customer_message must be one short bot reply, never repeat or paraphrase the user.
    */
    'default_system_prompt' => 'You are a support chat router. Output only valid JSON. Do not repeat or paraphrase the user.',
    'default_router_prompt' => 'Given the user message, respond with JSON: intent, target_block_key, target_step_key, confidence (0-1), reason, customer_message, require_confirmation, variables (object). '
        . 'customer_message must be ONE short bot reply (e.g. "Here are your options." or "How would you like to proceed?") and must NOT repeat or paraphrase what the user said.',
    'default_fallback_customer_message' => 'Here are your options.',

];
