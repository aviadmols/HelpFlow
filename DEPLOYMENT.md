# HelpFlow – Deployment Notes

## Railway (Railpack)

When deploying to **Railway** with Railpack, the build installs only a default set of PHP extensions. HelpFlow needs **intl** and **zip** (and **pcntl** for Horizon).

**Fix: set a build variable in your Railway project**

1. In Railway: open your project → **Variables** (or **Settings** → Variables).
2. Add a **build-time** variable (so it’s available during the build, not only at runtime):
   - **Name:** `RAILPACK_PHP_EXTENSIONS`
   - **Value:** `intl,zip,pcntl`
3. Redeploy. Railpack will install these extensions before running `composer install`.

If **pcntl** is not available (FrankenPHP/Railpack often does not include it), the project includes a **railpack.json** that runs `composer install` with `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix`, so the build succeeds. On that runtime you **cannot** run `php artisan horizon`; use **`php artisan queue:work`** (or a similar worker) for the queue instead.

The project also declares `ext-intl` and `ext-zip` in `composer.json` so that Railpack installs them; set `RAILPACK_PHP_EXTENSIONS=intl,zip` if needed.

## PHP and Composer

- **PHP**: 8.3 or 8.4. The lock file is resolved for PHP 8.3.30 (`config.platform.php` in `composer.json`).
- **Required extensions** (without them `composer install` fails on the server):
  - **ext-intl** – required by Filament (admin UI). Install or enable on the server.
  - **ext-zip** – required by openspout/Filament (exports). Install or enable on the server.
  - **ext-pcntl**, **ext-posix** – required by Laravel Horizon (queue). Not available on Windows; some Docker images omit them. If you cannot install them, use the ignore flags below and run `php artisan queue:work` instead of Horizon.

### If deploy fails with "ext-intl / ext-zip / ext-pcntl missing"

1. **Install extensions on the server (recommended)**  
   - **Ubuntu/Debian**: `sudo apt-get install -y php8.3-intl php8.3-zip` (and for Horizon: `php8.3-pcntl` if available).  
   - **Docker (Dockerfile)**: `RUN docker-php-ext-install intl zip pcntl` (or your PHP image’s equivalent).  
   - **Alpine**: `apk add php81-intl php81-zip` (and pcntl if needed).  
   Then run:
   ```bash
   composer install --optimize-autoloader --no-dev --no-scripts --no-interaction
   ```

2. **If you cannot install pcntl/posix** (e.g. minimal Docker), install intl and zip as above, then run Composer ignoring only the process extensions:
   ```bash
   composer install --optimize-autoloader --no-dev --no-scripts --no-interaction --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
   ```
   Use `php artisan queue:work` instead of `php artisan horizon` on that server.

3. **Temporary workaround (all extensions ignored)** – only if you cannot install intl/zip (Filament and exports may break):
   ```bash
   composer install --optimize-autoloader --no-dev --no-scripts --no-interaction --ignore-platform-req=ext-intl --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-zip
   ```

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
