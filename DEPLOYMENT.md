# HelpFlow – Deployment Notes

## Environment (.env)

- Set `APP_ENV=production`, `APP_DEBUG=false`, and a strong `APP_KEY`.
- **Database**: Use PostgreSQL in production. Set `DB_CONNECTION=pgsql` and credentials.
- **Queue**: Set `QUEUE_CONNECTION=redis` and Redis connection vars.
- **OpenRouter**: Set `OPENROUTER_API_KEY` for AI routing.
- **HelpFlow**: Optionally set `CHAT_CACHE_TTL`, `CHAT_DEFAULT_FLOW_KEY`, `CHAT_FALLBACK_BLOCK_KEY`.

## Queues and Horizon

1. Configure Redis and set `QUEUE_CONNECTION=redis`.
2. Run Horizon to process the `default` queue (including `RunApiActionJob`):

   ```bash
   php artisan horizon
   ```

   Use a process manager (e.g. systemd, Supervisor) to keep Horizon running.

3. Optional: separate queue for chat actions:

   In `config/horizon.php`, add a dedicated queue for `RunApiActionJob` if you want to isolate chat jobs.

## Realtime (Reverb)

1. Publish Reverb config and set `REVERB_*` and `VITE_REVERB_*` in `.env`.
2. Run Reverb server:

   ```bash
   php artisan reverb:start
   ```

   In production, run Reverb behind a reverse proxy (e.g. Nginx) with SSL and scale as needed.

## SSE

The `GET /api/chat/{id}/stream` endpoint uses Server-Sent Events. Ensure your web server does not buffer the response (e.g. Nginx: `proxy_buffering off` for this path).

## Logs and observability

- Log channels: `chat`, `ai_router`, `actions` (see `config/logging.php`). Rotate and retain per policy.
- AI telemetry is stored in `ai_telemetry`; use for token usage and routing analytics.
- Horizon dashboard: `/horizon` (protect with auth in production).

## Security

- Encrypt sensitive endpoint data (headers, auth_config) via the Endpoint model.
- Redact request/response in `action_runs` (handled in ActionRunner).
- Restrict Filament admin to trusted users (role admin/agent and strong auth).
- Use Sanctum or API tokens for chat API if you need to scope conversations to authenticated users.

## Migrations

Run migrations on deploy:

```bash
php artisan migrate --force
```

Seed default blocks/flows/endpoints only on first deploy or when adding new defaults:

```bash
php artisan db:seed --class=DefaultEndpointsSeeder
php artisan db:seed --class=DefaultFlowsSeeder
```

## Performance

- Block/flow config is cached per tenant; cache TTL is `CHAT_CACHE_TTL`. Clear cache after changing blocks/flows or use cache invalidation on save.
- Add DB indexes as in the plan (conversation_id, tenant_id, customer_id, status) if not already in migrations.
- Use Redis for cache and sessions in production.
