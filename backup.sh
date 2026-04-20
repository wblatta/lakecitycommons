#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"
BACKUP_DIR="$HOME/olyhillshub-backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env not found at $ENV_FILE" >&2
    exit 1
fi

get_env() {
    grep -E "^${1}=" "$ENV_FILE" | head -1 | sed "s/^${1}=//" | sed "s/^['\"]//;s/['\"]$//"
}

DB_HOST=$(get_env DB_HOST)
DB_DATABASE=$(get_env DB_DATABASE)
DB_USERNAME=$(get_env DB_USERNAME)
DB_PASSWORD=$(get_env DB_PASSWORD)

if [ -z "$DB_HOST" ] || [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
    echo "ERROR: Missing DB credentials in .env (DB_HOST, DB_DATABASE, DB_USERNAME required)" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"

# Database backup
DB_FILE="$BACKUP_DIR/db_${TIMESTAMP}.sql.gz"
echo "Backing up database..."
mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$DB_FILE"
echo "  Database: $DB_FILE ($(du -sh "$DB_FILE" | cut -f1))"

# Uploads backup
UPLOADS_SRC="$SCRIPT_DIR/storage/app/public"
if [ -d "$UPLOADS_SRC" ] && [ "$(ls -A "$UPLOADS_SRC" 2>/dev/null)" ]; then
    UPLOADS_DEST="$BACKUP_DIR/uploads_${TIMESTAMP}"
    echo "Backing up uploads..."
    cp -r "$UPLOADS_SRC" "$UPLOADS_DEST"
    echo "  Uploads: $UPLOADS_DEST ($(du -sh "$UPLOADS_DEST" | cut -f1))"
else
    echo "  No uploads to back up."
fi

echo "Backup complete. Files in: $BACKUP_DIR"
