<?php

use App\Support\ChatConstants;

test('AI router schema constants define required keys', function () {
    expect(ChatConstants::BLOCK_KEY_MAIN_MENU)->toBe('main_menu');
    expect(ChatConstants::ROUTER_FALLBACK_REASON)->toBe('fallback');
    expect(ChatConstants::actionTypes())->toContain('API_CALL', 'NEXT_STEP', 'HUMAN_HANDOFF');
});
