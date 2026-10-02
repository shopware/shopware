#!/usr/bin/env bash
# After shopware/setup-shopware has installed the reported version: start the dev server, optionally
# generate demodata, and emit the shop coordinates.
#
# Env: PREVIOUS_OUTCOME (guard), SHOP_DIR (default shop), DEMODATA (default false).
# Emits app_url, access_key.
set -euo pipefail

[ "${PREVIOUS_OUTCOME:-success}" = success ] || { echo "::error::shopware/setup-shopware failed; cannot continue."; exit 1; }
SHOP_DIR=${SHOP_DIR:-shop}
DEMODATA=${DEMODATA:-false}

{ echo "SHOPWARE_HTTP_CACHE_ENABLED=0"; echo "SHOPWARE_DISABLE_UPDATE_CHECK=true"; echo "BLUE_GREEN_DEPLOYMENT=1"; } >> "$GITHUB_ENV"

# Persist to the shop's .env.local so the DEV SERVER (started below) and both legs pick it up —
# a GITHUB_ENV write only reaches later steps, not the server we start in this one. Disabling the
# update check makes /api/_action/update/check return empty, so the Admin never shows the
# "new version available" banner that otherwise pops in async and intercepts clicks. Repro specs
# no longer need to dismiss it; a repro specifically about that banner is the rare exception.
grep -q '^SHOPWARE_DISABLE_UPDATE_CHECK=' "$SHOP_DIR/.env.local" 2>/dev/null \
  || printf '\nSHOPWARE_DISABLE_UPDATE_CHECK=1\n' >> "$SHOP_DIR/.env.local"

if [ "$DEMODATA" = true ]; then
  ( cd "$SHOP_DIR"; export APP_ENV=prod
    echo "::group::framework:demodata (bounded) + reindex"
    php bin/console framework:demodata --no-interaction --multiplier=0.1 --products=80 --orders=0 --reviews=0 --promotions=0
    php bin/console dal:refresh:index --no-interaction
    echo "::endgroup::" )
fi

( cd "$SHOP_DIR"; SYMFONY_DAEMON=1 SYMFONY_NO_TLS=1 SYMFONY_ALLOW_HTTP=1 SYMFONY_PORT=8000 SYMFONY_ALLOW_ALL_IP=1 symfony server:start )

APP_URL="http://localhost:8000"
for i in $(seq 1 60); do
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "$APP_URL/admin" || echo 000)
  [ "$code" != "000" ] && { echo "server responding (HTTP $code) after ${i}s"; ready=1; break; }
  sleep 1
done
[ "${ready:-0}" = 1 ] || { echo "::error::shop not ready after 60s"; exit 1; }

# rawurldecode user/pass so a percent-encoded userinfo (e.g. p%40ss -> p@ss) authenticates — matches
# reset.ts, which decodeURIComponent()s the same components.
eval "$(php -r '$u=parse_url(getenv("DATABASE_URL")); printf("DBH=%s DBP=%s DBU=%s DBPW=%s DBN=%s", $u["host"]??"127.0.0.1", $u["port"]??3306, rawurldecode($u["user"]??"root"), rawurldecode($u["pass"]??""), ltrim($u["path"]??"/","/"));')"
access_key=$(mysql -h"$DBH" -P"$DBP" -u"$DBU" ${DBPW:+-p"$DBPW"} "$DBN" -N -e "SELECT access_key FROM sales_channel WHERE active=1 ORDER BY created_at LIMIT 1;")
echo "::add-mask::$access_key"

{ echo "app_url=$APP_URL"; echo "access_key=$access_key"; } >> "$GITHUB_OUTPUT"
