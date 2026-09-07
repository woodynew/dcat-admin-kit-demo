#!/bin/sh
set -eu

cd /www
mkdir -p /data/storage
cp -a /opt/demo-storage/. /data/storage/

# This file belongs to this Compose volume, not to the host application's .env.
# Atomic rename avoids leaving an empty key if initial startup is interrupted.
if [ ! -s /data/runtime.env ]; then
    (umask 077; php -r 'echo "APP_KEY=base64:".base64_encode(random_bytes(32)).PHP_EOL;' > /data/runtime.env.tmp)
    mv /data/runtime.env.tmp /data/runtime.env
fi
ln -sf /data/runtime.env /www/.env

touch /data/database.sqlite
chown -R www-data:www-data /data/storage
chown www-data:www-data /data /data/database.sqlite /data/runtime.env
chmod 700 /data
chmod 600 /data/database.sqlite /data/runtime.env

# Use the app's idempotent install; a restart must never reset shared demo data.
php artisan config:clear --no-interaction
php artisan demo:install --no-interaction
php artisan config:cache --no-interaction
chown -R www-data:www-data /data/storage bootstrap/cache

exec "$@"
