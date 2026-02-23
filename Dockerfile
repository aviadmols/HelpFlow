# HelpFlow – production Docker image (PHP 8.3, intl/zip; composer install ignores pcntl).
# Use this if Railpack fails (e.g. pcntl not available). Run queue with: php artisan queue:work

# Stage 1: build frontend with Node 22 (apt node is too old for Vite 7 / Tailwind 4)
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci 2>/dev/null || npm install
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# Stage 2: PHP app
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

# Bring in built frontend assets from Node stage
COPY --from=frontend /app/public/build ./public/build

RUN php artisan package:discover --ansi

# Railway / Cloud: listen on PORT
ENV PORT=8000
EXPOSE 8000
CMD php artisan serve --host=0.0.0.0 --port=${PORT}
