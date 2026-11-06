#!/usr/bin/env bash
set -e

# Adjust UID/GID if provided
if [ -n "$WWWUSER" ] && [ "$WWWUSER" != "0" ]; then
    usermod -u "$WWWUSER" laravel 2>/dev/null || true
fi
if [ -n "$WWWGROUP" ] && [ "$WWWGROUP" != "0" ]; then
    groupmod -g "$WWWGROUP" laravel 2>/dev/null || true
fi

# Ensure .env exists
if [ ! -f /var/www/html/.env ]; then
    echo "==> Creating .env from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Ensure storage directories exist
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Fix permissions on storage and bootstrap/cache
chown -R laravel:laravel /var/www/html/storage /var/www/html/bootstrap/cache /home/laravel
chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

# Install Composer dependencies if missing
if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "==> Installing Composer dependencies..."
    gosu laravel composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Install NPM dependencies if missing
if [ ! -d "/var/www/html/node_modules" ]; then
    echo "==> Installing NPM dependencies..."
    gosu laravel npm install
fi

# Generate application key if not set
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
    echo "==> Generating application key..."
    gosu laravel php artisan key:generate --force
fi

# Wait for PostgreSQL if configured
if [ "${DB_CONNECTION}" = "pgsql" ] && [ -n "${DB_HOST}" ]; then
    echo "==> Waiting for PostgreSQL (${DB_HOST}:${DB_PORT:-5432})..."
    max_tries=30
    count=0
    until php -r "try { new PDO('pgsql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: 5432).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
        count=$((count+1))
        if [ $count -ge $max_tries ]; then
            echo "==> Warning: PostgreSQL did not respond within ${max_tries}s, continuing..."
            break
        fi
        sleep 1
    done
    if [ $count -lt $max_tries ]; then
        echo "==> PostgreSQL is ready!"
    fi
fi

# Wait for Redis if configured
if [ -n "${REDIS_HOST}" ]; then
    echo "==> Waiting for Redis (${REDIS_HOST}:${REDIS_PORT:-6379})..."
    max_tries=30
    count=0
    until php -r "try { \$r = new Redis(); \$r->connect(getenv('REDIS_HOST'), (int)(getenv('REDIS_PORT') ?: 6379), 1); exit(\$r->ping() ? 0 : 1); } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
        count=$((count+1))
        if [ $count -ge $max_tries ]; then
            echo "==> Warning: Redis did not respond within ${max_tries}s, continuing..."
            break
        fi
        sleep 1
    done
    if [ $count -lt $max_tries ]; then
        echo "==> Redis is ready!"
    fi
fi

# Run migrations if database is pgsql and reachable
if [ "${DB_CONNECTION}" = "pgsql" ] && [ -n "${DB_HOST}" ]; then
    echo "==> Running database migrations..."
    gosu laravel php artisan migrate --force || true
fi

# Execute CMD as laravel user
exec gosu laravel "$@"
