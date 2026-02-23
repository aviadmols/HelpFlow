# HelpFlow – production Docker image (PHP 8.3, intl/zip; composer install ignores pcntl).
# Use this if Railpack fails (e.g. pcntl not available). Run queue with: php artisan queue:work

FROM php:8.3-bookworm AS base

# Install system deps + PHP extensions (intl, zip, pdo_pgsql; no pcntl)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip libzip-dev libicu-dev libpq-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) intl zip pdo pdo_pgsql opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# Composer deps (ignore pcntl/posix so Horizon is not required at runtime)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction \
    --optimize-autoloader \
    --ignore-platform-req=ext-pcntl \
    --ignore-platform-req=ext-posix

# App code (vendor excluded via .dockerignore)
COPY . .

RUN php artisan package:discover --ansi

# Node for frontend build (no package-lock.json: use npm install)
RUN apt-get update && apt-get install -y --no-install-recommends nodejs npm \
    && npm install && npm run build \
    && rm -rf node_modules && apt-get clean && rm -rf /var/lib/apt/lists/*

# Railway / Cloud: listen on PORT
ENV PORT=8000
EXPOSE 8000
CMD php artisan serve --host=0.0.0.0 --port=${PORT}
