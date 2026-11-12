# syntax=docker/dockerfile:1

# One image runs everything Render's free plan allows in a single service:
# nginx + PHP-FPM for the site, the queue worker and the scheduler (see docker/).

FROM php:8.4-fpm-bookworm AS base

# poppler-utils provides pdftotext, the best extractor for résumé PDFs.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx supervisor gettext-base poppler-utils unzip \
        libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip intl opcache bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html


# Build stage: PHP dependencies, then the Vite build (Wayfinder runs artisan during it).
FROM base AS build

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .
# Wayfinder boots artisan during the Vite build, and Laravel needs an APP_KEY to boot.
# This throwaway key lives only for this command; the real one is set on Render at runtime.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" npm run build \
    && rm -rf node_modules


FROM base AS app

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/nginx.conf.template /etc/nginx/templates/app.conf.template
COPY docker/supervisord.conf /etc/supervisor/app.conf
COPY docker/start.sh /usr/local/bin/start

COPY --from=build /var/www/html /var/www/html

RUN rm -f /etc/nginx/sites-enabled/default \
    && chmod +x /usr/local/bin/start \
    && chown -R www-data:www-data storage bootstrap/cache

# Render sets PORT; 10000 is its default.
ENV PORT=10000
EXPOSE 10000

CMD ["start"]
