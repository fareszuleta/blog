#!/bin/sh
# Runs as root at container start. Mounted volumes (e.g. a Render disk) can arrive
# owned by root, so make the SQLite data directory writable by www-data first.
set -e
mkdir -p /var/www/data
chown -R www-data:www-data /var/www/data
# Hand off to the image's normal startup (apache2-foreground) with the original command.
exec docker-php-entrypoint "$@"
