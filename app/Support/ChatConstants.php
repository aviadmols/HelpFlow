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

    // Input types for orchestrator
    public const INPUT_TYPE_TEXT = 'text';

    public const INPUT_TYPE_OPTION_CLICK = 'option_click';

    // Router fallback
    public const ROUTER_FALLBACK_REASON = 'fallback';

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
