#!/usr/bin/env bash
set -euo pipefail

APP_PATH="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
SUPERVISOR_DIR="/etc/supervisor/conf.d"

if [[ ! -f "$APP_PATH/artisan" ]]; then
    echo "Path aplikasi tidak valid: $APP_PATH"
    exit 1
fi

if ! command -v supervisorctl &>/dev/null; then
    echo "Supervisor belum terpasang. Install: apt install supervisor"
    exit 1
fi

sed "s|__APP_PATH__|$APP_PATH|g" "$APP_PATH/deploy/supervisor/backup-manager-worker.conf" \
    | sudo tee "$SUPERVISOR_DIR/backup-manager-worker.conf" > /dev/null

sed "s|__APP_PATH__|$APP_PATH|g" "$APP_PATH/deploy/supervisor/backup-manager-scheduler.conf" \
    | sudo tee "$SUPERVISOR_DIR/backup-manager-scheduler.conf" > /dev/null

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start backup-manager-worker:* backup-manager-scheduler 2>/dev/null || true

echo "Supervisor terpasang. Status:"
sudo supervisorctl status | grep backup-manager || true
