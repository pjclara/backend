#!/bin/bash
# Deploy script — corre na pasta laravel/ no servidor Hostinger
set -e

LARAVEL_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$LARAVEL_DIR"

echo "==> git pull"
git pull

echo "==> composer install"
composer install --no-dev --optimize-autoloader --quiet

echo "==> migrate"
php artisan migrate --force

echo "==> cache"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> sync public/ -> ../public_html/"
rsync -a --delete public/ ../public_html/

echo "==> fix index.php paths"
sed -i "s|__DIR__\.'/../|'${LARAVEL_DIR}/|g" ../public_html/index.php

echo "==> DONE"
