#!/usr/bin/env bash
# Runs on the production host, called by the "Deploy to production" job in
# .github/workflows/tests.yml. Unpacks a release built in CI, links the shared
# .env and storage, runs migrations, then switches "current" to the new release.
#
# Usage: remote-deploy.sh <app dir> <release name> [php binary]
#
# Layout under <app dir>:
#   current  -> releases/<latest>   (the subdomain's document root is current/public)
#   releases/<name>/                (last 5 kept, for rollback)
#   shared/.env                     (created once by hand, see DEPLOY.md)
#   shared/storage/                 (uploads, logs, sessions; survives deploys)
#   uploads/                        (temporary: the release archive and this script)
set -euo pipefail

APP_DIR="$1"
RELEASE="$2"
PHP_BIN="${3:-php}"

RELEASES="$APP_DIR/releases"
SHARED="$APP_DIR/shared"
NEW="$RELEASES/$RELEASE"
ARCHIVE="$APP_DIR/uploads/release.tgz"

if [ ! -f "$SHARED/.env" ]; then
    echo "Missing $SHARED/.env. Create it from deploy/.env.production.example first (see DEPLOY.md)." >&2
    exit 1
fi

"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || {
    echo "$PHP_BIN is PHP $("$PHP_BIN" -r 'echo PHP_VERSION;'), but the app needs 8.3 or newer. Set the DEPLOY_PHP variable to the host's PHP 8.3+ binary." >&2
    exit 1
}

mkdir -p "$RELEASES" \
    "$SHARED/storage/app/public" "$SHARED/storage/app/private" \
    "$SHARED/storage/framework/cache/data" "$SHARED/storage/framework/sessions" \
    "$SHARED/storage/framework/views" "$SHARED/storage/logs"

echo "Unpacking release $RELEASE"
mkdir -p "$NEW"
tar -xzf "$ARCHIVE" -C "$NEW"
rm -f "$ARCHIVE"

ln -sfn "$SHARED/.env" "$NEW/.env"
rm -rf "$NEW/storage"
ln -sfn "$SHARED/storage" "$NEW/storage"
mkdir -p "$NEW/bootstrap/cache"

cd "$NEW"
"$PHP_BIN" artisan storage:link --force --no-interaction
"$PHP_BIN" artisan migrate --force --no-interaction
"$PHP_BIN" artisan optimize --no-interaction

echo "Switching current -> $RELEASE"
ln -sfn "$NEW" "$APP_DIR/current.new"
mv -Tf "$APP_DIR/current.new" "$APP_DIR/current"

# Keep the five newest releases.
cd "$RELEASES"
ls -1t | tail -n +6 | while read -r old; do rm -rf -- "$RELEASES/$old"; done

rm -f "$APP_DIR/uploads/remote-deploy.sh"
echo "Deployed $RELEASE"
