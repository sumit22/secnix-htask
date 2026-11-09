# syntax=docker/dockerfile:1.7

# ---------------------------------------------------------------------------
# base: FrankenPHP (classic mode) + PHP extensions, non-root user
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1.13.5-php8.5.12-alpine AS base

ARG APP_UID=10001
ARG APP_GID=10001

# Caddy needs writable config/data dirs: /tmp works with a read-only root fs + tmpfs /tmp.
ENV SERVER_NAME=:8000 \
    XDG_CONFIG_HOME=/tmp/caddy/config \
    XDG_DATA_HOME=/tmp/caddy/data \
    COMPOSER_ALLOW_SUPERUSER=1

RUN install-php-extensions pdo_pgsql opcache intl pcntl zip \
    && apk add --no-cache libcap-utils \
    # setcap-less operation: port 8000 > 1024, and file caps break under no-new-privileges
    && setcap -r /usr/local/bin/frankenphp \
    && apk del libcap-utils \
    && addgroup -g ${APP_GID} -S app \
    && adduser -u ${APP_UID} -S -G app -h /app -s /sbin/nologin app \
    && mkdir -p /app \
    && chown app:app /app

WORKDIR /app
EXPOSE 8000

# ---------------------------------------------------------------------------
# deps: production vendor tree (cached composer downloads)
# ---------------------------------------------------------------------------
FROM base AS deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---------------------------------------------------------------------------
# dev: xdebug + dev deps; source is bind-mounted at /app by compose
# ---------------------------------------------------------------------------
FROM base AS dev
RUN install-php-extensions xdebug \
    && apk add --no-cache git unzip \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug-app.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# XDEBUG_MODE: off (default) | debug | develop | coverage ...
ENV XDEBUG_MODE=off
EXPOSE 9003
USER app
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]

# ---------------------------------------------------------------------------
# prod: optimized, non-root, only storage/ and bootstrap/cache writable
# ---------------------------------------------------------------------------
FROM base AS prod
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/opcache.prod.ini /usr/local/etc/php/conf.d/zz-opcache-prod.ini

COPY --from=deps /app/vendor ./vendor
COPY . .

RUN --mount=type=bind,from=composer:2,source=/usr/bin/composer,target=/usr/bin/composer \
    composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R u+rwX,go-w storage bootstrap/cache

USER app
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD wget -q --spider http://localhost:8000/up || exit 1
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
