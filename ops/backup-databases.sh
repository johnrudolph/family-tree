#!/usr/bin/env bash
# Daily backup of this server's databases (family-tree + catacombian) to
# Cloudflare R2. Lives and runs directly on the Forge server, outside the
# Laravel deploy pipeline — copy it to ~/scripts/backup-databases.sh there.
# This file is kept here purely for version history; editing it in the repo
# does not affect the server. Scheduled via Forge's Scheduler (Server →
# Scheduler), not the gated Backups tab (Business-plan only).
#
# Requires ~/scripts/.backup-env alongside it on the server (chmod 600,
# never committed) — see backup-databases.env.example for the format.
set -euo pipefail

source "$(dirname "$0")/.backup-env"

BACKUP_DIR="/home/forge/db-backups"
TIMESTAMP=$(date +%Y-%m-%d-%H%M%S)
RETENTION_DAYS=14

mkdir -p "$BACKUP_DIR"

backup_database() {
    local label="$1" db_name="$2" db_user="$3" db_pass="$4"
    local file="$BACKUP_DIR/${label}-${TIMESTAMP}.sql.gz"

    echo "Dumping ${label} (${db_name})..."
    MYSQL_PWD="$db_pass" mysqldump --single-transaction --quick --no-tablespaces \
        -u "$db_user" "$db_name" | gzip > "$file"

    echo "Uploading ${label} to R2..."
    aws s3 cp "$file" "s3://${R2_BUCKET}/${label}/$(basename "$file")" \
        --endpoint-url "$R2_ENDPOINT"

    rm -f "$file"
}

backup_database "family-tree" "$FAMILY_TREE_DB_NAME" "$FAMILY_TREE_DB_USER" "$FAMILY_TREE_DB_PASS"
backup_database "catacombian" "$CATACOMBIAN_DB_NAME" "$CATACOMBIAN_DB_USER" "$CATACOMBIAN_DB_PASS"

echo "Pruning backups older than ${RETENTION_DAYS} days..."
CUTOFF=$(date -d "-${RETENTION_DAYS} days" +%Y-%m-%d)
for label in family-tree catacombian; do
    aws s3 ls "s3://${R2_BUCKET}/${label}/" --endpoint-url "$R2_ENDPOINT" | while read -r _ _ _ fname; do
        [ -z "$fname" ] && continue
        fdate=$(echo "$fname" | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}' | head -1)
        if [[ "$fdate" < "$CUTOFF" ]]; then
            echo "Deleting old backup: $fname"
            aws s3 rm "s3://${R2_BUCKET}/${label}/${fname}" --endpoint-url "$R2_ENDPOINT"
        fi
    done
done

echo "Backup complete."
