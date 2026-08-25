# ── Stage 1: PHP dependencies ────────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts \
    --prefer-dist --optimize-autoloader --ignore-platform-reqs

# ── Stage 2: Runtime ─────────────────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.4 AS runtime

RUN install-php-extensions opcache

# HTTP only on 8080 — TLS terminates at Cloudflare.
ENV SERVER_NAME=:8080

# State lives in the container filesystem and resets when the pod restarts.
# That is intentional: a device that forgets its actuator positions is the
# cleanest way to demonstrate the reconciler putting them back.
ENV STATE_FILE=/tmp/greenhouse-state.json

WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY src ./src
COPY public ./public
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

RUN chown -R www-data:www-data /data /config

USER www-data
EXPOSE 8080

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
