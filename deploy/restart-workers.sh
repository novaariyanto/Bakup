#!/usr/bin/env bash
set -euo pipefail

APP_PATH="${1:-$(cd "$(dirname "$0")/.." && pwd)}"

cd "$APP_PATH"
php artisan queue:restart

if command -v supervisorctl &>/dev/null; then
    sudo supervisorctl restart backup-manager-worker:* || true
fi

echo "Queue workers restarted."
