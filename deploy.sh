#!/usr/bin/env bash
#
# Deploy the latest code from GitHub on the server.
#
#   ./deploy.sh              full deploy
#   ./deploy.sh --no-build   skip "npm ci && npm run build" (PHP/Blade-only changes)
#
# Run it as your normal user from anywhere; it works inside the project folder.
# It needs sudo only for fixing permissions and restarting the queue worker.

set -euo pipefail

cd "$(dirname "$0")"

BUILD=1
for arg in "$@"; do
    case "$arg" in
        --no-build) BUILD=0 ;;
        -h|--help) sed -n '2,10p' "$0"; exit 0 ;;
        *) echo "Unknown option: $arg (use --no-build or --help)"; exit 1 ;;
    esac
done

if [ "$(id -u)" -eq 0 ]; then
    echo "Do not run this as root: files would end up owned by root."
    exit 1
fi

step() { printf '\n==> %s\n' "$1"; }

step "Fetching the latest code"
BEFORE=$(git rev-parse --short HEAD)
git pull --ff-only
AFTER=$(git rev-parse --short HEAD)

if [ "$BEFORE" = "$AFTER" ]; then
    echo "Already up to date ($AFTER)."
else
    echo "Updated $BEFORE -> $AFTER"
    git --no-pager log --oneline "$BEFORE..$AFTER"
fi

step "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

if [ "$BUILD" -eq 1 ]; then
    step "Building frontend assets"
    npm ci
    npm run build
else
    step "Skipping the frontend build (--no-build)"
fi

step "Running database migrations"
php artisan migrate --force

step "Refreshing caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

step "Fixing permissions"
sudo chown -R www-data:www-data storage bootstrap/cache

step "Restarting the queue worker"
if systemctl list-unit-files books-queue.service >/dev/null 2>&1 && systemctl cat books-queue >/dev/null 2>&1; then
    sudo systemctl restart books-queue
    systemctl is-active books-queue
else
    echo "books-queue service not found - skipping (recommendations need a running queue worker)."
fi

step "Checking the site"
URL=$(grep -E '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d '"')
if [ -n "$URL" ]; then
    CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL/login" || true)
    echo "$URL/login -> HTTP $CODE"
    [ "$CODE" = "200" ] || { echo "Unexpected response. Check: tail -n 30 storage/logs/laravel.log"; exit 1; }
fi

printf '\nDone. Now running %s.\n' "$AFTER"
