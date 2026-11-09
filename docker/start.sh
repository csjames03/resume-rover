#!/bin/sh
# Container entrypoint: prepare Laravel, update the database, start everything.
set -e
cd /var/www/html

# Render passes configuration as environment variables; cache it once per boot.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan migrate --force
php artisan app:seed-defaults

envsubst '${PORT}' < /etc/nginx/templates/app.conf.template > /etc/nginx/conf.d/app.conf

chown -R www-data:www-data storage bootstrap/cache

exec supervisord -c /etc/supervisor/app.conf
