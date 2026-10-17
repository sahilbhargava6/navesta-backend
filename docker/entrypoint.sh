#!/bin/sh
# Prepares the container, then runs its command (web server, queue worker or scheduler).
set -e

# Secrets mounted as files (Docker/Kubernetes secrets, or a cloud secrets
# manager's file mount) become environment variables: /run/secrets/app_key
# -> APP_KEY. A variable that is already set is left alone.
if [ -d /run/secrets ]; then
    for file in /run/secrets/*; do
        [ -f "$file" ] || continue
        name=$(basename "$file" | tr '[:lower:]-' '[:upper:]_')
        if [ -z "$(printenv "$name")" ]; then
            export "$name=$(cat "$file")"
        fi
    done
fi

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

# Configuration comes from the container environment; cache it for speed
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Public media (blog images) served from storage/app/public
if [ ! -e public/storage ]; then
    php artisan storage:link
fi

# Single-server setups let the API container migrate on start (RUN_MIGRATIONS=true).
# With several API replicas, run migrations once from the deploy script instead.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
