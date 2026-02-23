# HelpFlow

HelpFlow is a production-ready, configurable Customer Support Chat system: Laravel backend, Filament admin, Option Blocks, OpenRouter AI routing, Action Runner, full audit trail, and realtime (SSE / Reverb).

All UI, admin, database content, prompts, logs, and documentation are in **English only**.

## Requirements

- PHP 8.3+
- Composer
- PostgreSQL (recommended) or SQLite (local)
- Redis (for queues and Horizon in production)
- Node/npm (for Filament assets)

## Setup

1. **Clone and install**

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. **Database**

   For PostgreSQL, set in `.env`:

   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=helpflow
   DB_USERNAME=postgres
   DB_PASSWORD=yourpassword
   ```

   Then:

   ```bash
   php artisan migrate
   php artisan db:seed
   ```

3. **OpenRouter (AI routing)**

   Get an API key from [OpenRouter](https://openrouter.ai) and set:

   ```env
   OPENROUTER_API_KEY=your_key
   ```

4. **Admin user**

   After seeding, log in at `/admin` with:

   - Email: `admin@example.com`
   - Password: (from `User::factory()`; run `php artisan tinker` and reset if needed)

5. **Queue (production)**

   ```env
   QUEUE_CONNECTION=redis
   ```

   Run Horizon:

   ```bash
   php artisan horizon
   ```

6. **Optional: Reverb (WebSockets)**

   Publish and configure Reverb, then run:

   ```bash
   php artisan reverb:start
   ```

## How to add new blocks, flows, and endpoints

### Blocks

1. Go to **Admin → Blocks** and create a block (key, title, message template).
2. In the block’s **Options** relation, add buttons: label, `action_type` (e.g. `NEXT_STEP`, `API_CALL`, `HUMAN_HANDOFF`).
3. For `API_CALL`, attach an **Endpoint** and set success/failure templates and optional `next_step_on_failure`.

### Flows

1. Go to **Admin → Flows** and create a flow (key, name, router_prompt, system_prompt, default_model).
2. In **Steps**, add steps with keys, bot message templates, and **allowed blocks** (and fallback block).
3. In block options, set **next_step_id** / **confirm_step_id** to these steps.

### Endpoints

1. Go to **Admin → Endpoints** and create an endpoint (method, URL, request_mapper, response_mapper).
2. Use **request_mapper** to map context/customer fields into the request body.
3. Use **response_mapper** with dot paths (e.g. `discount.code`) to map the response into variables for templates.

## API (customer-facing)

- `POST /api/chat/start` – Body: `email`, `name`, optional `flow_key`, optional `customer_id`. Returns `conversation_id`, `block`.
- `POST /api/chat/{id}/message` – Body: `message`. Returns new messages and updated block (and optional `action_status`).
- `POST /api/chat/{id}/option` – Body: `option_id`. Returns new messages and block (or `action_status` for queued API actions).
- `GET /api/chat/{id}/stream` – SSE stream of new messages and action status updates.

## HelpFlow project structure (main parts)

- `app/Support/ChatConstants.php` – All action types, statuses, keys (no magic strings).
- `app/Services/Chat/` – ConversationOrchestrator, BlockPresenter, AIRouter, ActionRunner, TemplateRenderer, ResponseMapper.
- `app/Services/OpenRouter/OpenRouterClient.php` – OpenRouter API client.
- `app/Jobs/RunApiActionJob.php` – Queued API_CALL execution.
- `app/Filament/` – Admin resources (Blocks, Flows, Endpoints, Conversations, Tickets) and pages (Conversation Viewer, AI Log).

## Testing

```bash
composer test
./vendor/bin/pint
./vendor/bin/phpstan analyse app
```

## English only

All user-facing and developer-facing text (code comments, prompts, admin labels, templates, logs, docs) are in English only.
