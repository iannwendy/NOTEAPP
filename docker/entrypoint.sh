#!/bin/bash
set -e

# Create a default .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "Creating basic .env file..."
    cat > .env << EOF
APP_NAME=NotesApp
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=${APP_URL:-https://noteapp-7orp.onrender.com}
ASSET_URL=${ASSET_URL:-}

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=${DB_CONNECTION:-mysql}
DB_HOST=${DB_HOST:-mysql}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-laravel}
DB_USERNAME=${DB_USERNAME:-root}
DB_PASSWORD=${DB_PASSWORD:-}

BROADCAST_DRIVER=${BROADCAST_DRIVER:-pusher}
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

PUSHER_APP_ID=${PUSHER_APP_ID:-1976166}
PUSHER_APP_KEY=${PUSHER_APP_KEY:-aa6e129c9fdc2f8333c3}
PUSHER_APP_SECRET=${PUSHER_APP_SECRET:-0c038050207fccdaed34}
PUSHER_HOST=${PUSHER_HOST:-noteapp-7orp.onrender.com}
PUSHER_PORT=${PUSHER_PORT:-443}
PUSHER_SCHEME=${PUSHER_SCHEME:-https}
PUSHER_APP_CLUSTER=${PUSHER_APP_CLUSTER:-ap1}

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

FORCE_HTTPS=true
EOF
fi

# Generate application key if not set
if grep -q "APP_KEY=\s*$" .env; then
    echo "Generating application key..."
    php artisan key:generate
fi

# Run migrations (with --force to run in production)
echo "Running database migrations..."
php artisan migrate --force

# Create storage link if it doesn't exist
if [ ! -L public/storage ]; then
    echo "Creating storage symlink..."
    php artisan storage:link
fi

# Clear and cache configuration
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Check for Vite manifest and copy it to the expected location if needed
if [ ! -f public/build/manifest.json ] && [ -f public/build/.vite/manifest.json ]; then
    echo "Copying Vite manifest from .vite directory to build directory..."
    mkdir -p public/build
    cp public/build/.vite/manifest.json public/build/manifest.json
fi

# Verify Vite manifest exists
if [ ! -f public/build/manifest.json ]; then
    echo "WARNING: Vite manifest not found. Assets might not be built correctly."
    # Try to build assets again if manifest is missing
    echo "Attempting to rebuild assets..."
    npm ci && npm run build
    # Check if manifest was created in .vite directory and copy it
    if [ -f public/build/.vite/manifest.json ]; then
        echo "Copying Vite manifest from .vite directory to build directory..."
        cp public/build/.vite/manifest.json public/build/manifest.json
    fi
fi

# Fix permissions
echo "Setting file permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/public/build

# Set up supervisor if configuration exists
if [ -d "/etc/supervisor/conf.d" ]; then
    echo "Setting up supervisor..."
    cp -f /var/www/html/docker/supervisor/*.conf /etc/supervisor/conf.d/
    supervisord -c /etc/supervisor/supervisord.conf
fi

# Execute the passed command
echo "Starting application..."
exec "$@" 