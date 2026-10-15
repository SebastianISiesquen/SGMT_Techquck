#!/usr/bin/env bash
# Respaldo diario de base de datos y archivos (RNF05).
# Cron (como root):  30 2 * * * /var/www/sggp/backend/deploy/respaldo.sh >> /var/log/sggp-respaldo.log 2>&1
set -euo pipefail

APP=/var/www/sggp/backend
DESTINO=/var/backups/sggp
DIAS_CONSERVAR=30
FECHA=$(date +%Y%m%d-%H%M)

# Lee las credenciales del .env de Laravel
DB_DATABASE=$(grep ^DB_DATABASE= "$APP/.env" | cut -d= -f2-)
DB_USERNAME=$(grep ^DB_USERNAME= "$APP/.env" | cut -d= -f2-)
DB_PASSWORD=$(grep ^DB_PASSWORD= "$APP/.env" | cut -d= -f2- | tr -d '"')

mkdir -p "$DESTINO"

MYSQL_PWD="$DB_PASSWORD" mysqldump -u "$DB_USERNAME" --single-transaction --routines "$DB_DATABASE" | gzip > "$DESTINO/bd-$FECHA.sql.gz"
tar -czf "$DESTINO/archivos-$FECHA.tar.gz" -C "$APP/storage/app" private

find "$DESTINO" -type f -mtime +$DIAS_CONSERVAR -delete
echo "$(date) Respaldo OK: bd-$FECHA.sql.gz, archivos-$FECHA.tar.gz"

# Recomendado: copiar $DESTINO fuera del servidor (rclone a Google Drive / S3).

# Restauración:
#   gunzip < bd-AAAAMMDD-HHMM.sql.gz | mysql -u USUARIO -p BASEDATOS
#   tar -xzf archivos-AAAAMMDD-HHMM.tar.gz -C /var/www/sggp/backend/storage/app
