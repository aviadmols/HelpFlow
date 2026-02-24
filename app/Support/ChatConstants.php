<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized constants for the HelpFlow Customer Support Chat system.
 * Use these instead of magic strings/numbers everywhere.
 */
final class ChatConstants
{
    // Action types for block options
    public const ACTION_TYPE_API_CALL = 'API_CALL';

    public const ACTION_TYPE_NEXT_STEP = 'NEXT_STEP';

    public const ACTION_TYPE_CONFIRM = 'CONFIRM';

    public const ACTION_TYPE_HUMAN_HANDOFF = 'HUMAN_HANDOFF';

    public const ACTION_TYPE_OPEN_URL = 'OPEN_URL';

    public const ACTION_TYPE_NO_OP = 'NO_OP';

    /** Run an AI prompt via OpenRouter/ChatGPT; response shown to user and optionally stored in context. */
    public const ACTION_TYPE_RUN_PROMPT = 'RUN_PROMPT';

    // Message roles
    public const MESSAGE_ROLE_CUSTOMER = 'customer';

    public const MESSAGE_ROLE_BOT = 'bot';

    public const MESSAGE_ROLE_AGENT = 'agent';

    // Message types
    public const MESSAGE_TYPE_TEXT = 'text';

    public const MESSAGE_TYPE_OPTION_CLICK = 'option_click';

    public const MESSAGE_TYPE_SYSTEM = 'system';

    // Action run statuses
    public const RUN_STATUS_QUEUED = 'queued';

    public const RUN_STATUS_RUNNING = 'running';

    public const RUN_STATUS_SUCCESS = 'success';

    public const RUN_STATUS_FAILED = 'failed';

    // Conversation statuses
    public const CONVERSATION_STATUS_ACTIVE = 'active';

    public const CONVERSATION_STATUS_HANDOFF = 'handoff';

    public const CONVERSATION_STATUS_CLOSED = 'closed';

    // Ticket statuses
    public const TICKET_STATUS_OPEN = 'open';

    public const TICKET_STATUS_IN_PROGRESS = 'in_progress';

    public const TICKET_STATUS_RESOLVED = 'resolved';

    // User roles (admin dashboard)
    public const USER_ROLE_ADMIN = 'admin';

    public const USER_ROLE_AGENT = 'agent';

    // Default block keys (seeders / fallback)
    public const BLOCK_KEY_MAIN_MENU = 'main_menu';

    public const BLOCK_KEY_SUBSCRIPTION_MANAGEMENT = 'subscription_management';

    public const BLOCK_KEY_CONFIRMATION = 'confirmation';

    public const BLOCK_KEY_WHAT_NEXT = 'what_next';

    // Default step keys
    public const STEP_KEY_MAIN_MENU = 'main_menu';

    public const STEP_KEY_CONFIRM_CANCEL_SUBSCRIPTION = 'confirm_cancel_subscription';

    public const STEP_KEY_SUBSCRIPTION_EDIT_MENU = 'subscription_edit_menu';

    public const STEP_KEY_HANDOFF_OFFER = 'handoff_offer';

    // Step tone of speech (for AI router and bot messages)
    public const TONE_FRIENDLY = 'friendly';

    public const TONE_FORMAL = 'formal';

    public const TONE_CONCISE = 'concise';

    public const TONE_EMPATHETIC = 'empathetic';

    public const TONE_PROFESSIONAL = 'professional';

    public const TONE_CUSTOM = 'custom';

    // Input types for orchestrator
    public const INPUT_TYPE_TEXT = 'text';

    public const INPUT_TYPE_OPTION_CLICK = 'option_click';

    // Router fallback
    public const ROUTER_FALLBACK_REASON = 'fallback';

    /** @return array<string, string> Tone key => label for UI */
    public static function toneOptions(): array
    {
        return [
            self::TONE_FRIENDLY => 'Friendly',
            self::TONE_FORMAL => 'Formal',
            self::TONE_CONCISE => 'Concise',
            self::TONE_EMPATHETIC => 'Empathetic',
            self::TONE_PROFESSIONAL => 'Professional',
            self::TONE_CUSTOM => 'Custom (use instructions below)',
        ];
    }

    /** Human-readable tone description for AI prompt. Returns null if no tone set. */
    public static function toneDescriptionForPrompt(?string $tone, ?string $toneInstructions): ?string
    {
        if ($tone === null || $tone === '') {
            return null;
        }
        if ($tone === self::TONE_CUSTOM && $toneInstructions !== null && trim($toneInstructions) !== '') {
            return trim($toneInstructions);
        }
        $descriptions = [
            self::TONE_FRIENDLY => 'Friendly and warm; use casual, approachable language.',
            self::TONE_FORMAL => 'Formal and polite; use professional, respectful language.',
            self::TONE_CONCISE => 'Concise and to the point; keep replies short and clear.',
            self::TONE_EMPATHETIC => 'Empathetic and understanding; acknowledge feelings and show care.',
            self::TONE_PROFESSIONAL => 'Professional and neutral; clear and business-appropriate.',
        ];

        return $descriptions[$tone] ?? null;
    }

    /** @return array<string> */
    public static function actionTypes(): array
    {
        return [
            self::ACTION_TYPE_API_CALL,
            self::ACTION_TYPE_NEXT_STEP,
            self::ACTION_TYPE_CONFIRM,
            self::ACTION_TYPE_HUMAN_HANDOFF,
            self::ACTION_TYPE_OPEN_URL,
            self::ACTION_TYPE_NO_OP,
            self::ACTION_TYPE_RUN_PROMPT,
        ];
    }

    /** @return array<string> */
    public static function runStatuses(): array
    {
        return [
            self::RUN_STATUS_QUEUED,
            self::RUN_STATUS_RUNNING,
            self::RUN_STATUS_SUCCESS,
            self::RUN_STATUS_FAILED,
        ];
    }

    /** @return array<string> */
    public static function conversationStatuses(): array
    {
        return [
            self::CONVERSATION_STATUS_ACTIVE,
            self::CONVERSATION_STATUS_HANDOFF,
            self::CONVERSATION_STATUS_CLOSED,
        ];
    }
}
