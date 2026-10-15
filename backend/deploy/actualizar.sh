#!/usr/bin/env bash
# Publica una nueva versión en el servidor:  bash deploy/actualizar.sh
set -euo pipefail
cd "$(dirname "$0")/.."

php artisan down --retry=30 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=RolesPermisosSeeder --force   # agrega permisos nuevos sin tocar los existentes
php artisan db:seed --class=ChecklistsSeeder --force      # checklists iniciales (no pisa los editados)
php artisan optimize
php artisan up
echo "Versión publicada."
