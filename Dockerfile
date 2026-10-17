# Navesta API — production image: Laravel Octane on FrankenPHP (PHP 8.3).
# Octane keeps the app booted between requests (several times the throughput
# of a classic PHP server). The same image runs the web API, the queue
# workers and the scheduler; docker-compose.yml picks the role with `command`.
FROM dunglas/frankenphp:1-php8.3

# PHP extensions + PostgreSQL 17 client tools (pg_dump / psql, used by backups)
RUN set -eux; \
    install-php-extensions pdo_pgsql intl zip bcmath pcntl opcache; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl gnupg unzip; \
    install -d /usr/share/postgresql-common/pgdg; \
    curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc; \
    . /etc/os-release; \
    echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt ${VERSION_CODENAME}-pgdg main" > /etc/apt/sources.list.d/pgdg.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends postgresql-client-17; \
    apt-get purge -y --auto-remove gnupg; \
    rm -rf /var/lib/apt/lists/*

# PHP: production defaults, plus upload limits for statements (10 MB) and OPcache
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/navesta.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies first so code changes don't invalidate this layer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN set -eux; \
    composer dump-autoload --optimize --classmap-authoritative --no-dev; \
    php artisan package:discover --ansi; \
    php artisan filament:assets; \
    mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; \
    useradd --system --uid 1000 --home /app navesta; \
    chown -R navesta:navesta storage bootstrap/cache /data /config

COPY docker/entrypoint.sh /usr/local/bin/navesta-entrypoint
RUN chmod +x /usr/local/bin/navesta-entrypoint

# Run as an unprivileged user; port 8000 needs no root
USER navesta
ENV OCTANE_SERVER=frankenphp
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s CMD curl -fsS http://localhost:8000/health > /dev/null || exit 1

ENTRYPOINT ["navesta-entrypoint"]
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=auto", "--max-requests=1000"]
