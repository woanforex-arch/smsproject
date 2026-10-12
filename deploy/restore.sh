#!/usr/bin/env bash
###############################################################################
#  Restore a backup created by event-tickets-backup
#
#  Usage: sudo bash deploy/restore.sh /var/backups/event-tickets/db-20260101-020000.sql.gz
###############################################################################
set -euo pipefail

BACKUP_FILE="${1:-}"

if [ -z "${BACKUP_FILE}" ] || [ ! -f "${BACKUP_FILE}" ]; then
    echo "Usage: $0 <path-to-backup.sql.gz>"
    exit 1
fi

APP_DIR="${APP_DIR:-/var/www/event-tickets}"

cd "${APP_DIR}"

read -rp "This will OVERWRITE the current database. Type 'yes' to continue: " CONFIRM
if [ "${CONFIRM}" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_USER=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_PASS=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_HOST=$(grep -E '^DB_HOST=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")

echo "Restoring '${BACKUP_FILE}' into database '${DB_NAME}'..."
gunzip -c "${BACKUP_FILE}" | mysql \
    --host="${DB_HOST}" \
    --user="${DB_USER}" \
    --password="${DB_PASS}" \
    "${DB_NAME}"

php artisan optimize:clear
supervisorctl restart event-tickets:*

echo "Restore complete."