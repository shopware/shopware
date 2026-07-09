#!/usr/bin/env bash
# Register an ADDITIONAL storefront sales-channel domain so the SANDBOXED agent can reach the shop at
# its host.docker.internal URL. The install only registers the host-side (localhost) URL, so a
# storefront request arriving with a different Host is rejected with HTTP 400
# (SalesChannelMappingException). This is additive: the existing localhost domain keeps serving the
# trusted host-side legs (reported-verify + trunk).
#
# Env: SHOP_DIR (default shop), SANDBOX_URL (required, e.g. http://host.docker.internal:8000).
set -euo pipefail

SHOP_DIR=${SHOP_DIR:-shop}
: "${SANDBOX_URL:?SANDBOX_URL is required}"
cd "$SHOP_DIR"

# Same DATABASE_URL parsing as finish-provision.sh / reset.mjs (rawurldecode user/pass).
eval "$(php -r '$u=parse_url(getenv("DATABASE_URL")); printf("DBH=%s DBP=%s DBU=%s DBPW=%s DBN=%s", $u["host"]??"127.0.0.1", $u["port"]??3306, rawurldecode($u["user"]??"root"), rawurldecode($u["pass"]??""), ltrim($u["path"]??"/","/"));')"
my() { mysql -h"$DBH" -P"$DBP" -u"$DBU" ${DBPW:+-p"$DBPW"} "$DBN" "$@"; }

# SALES_CHANNEL_TYPE_STOREFRONT = 8a243080f92e4c719546314b577cf82b (stable across 6.x). Clone an
# existing storefront domain's language/currency/snippet set and only swap the URL, so the new domain
# is valid without hard-coding those ids. Idempotent via NOT EXISTS.
my <<SQL
INSERT INTO sales_channel_domain (id, sales_channel_id, language_id, currency_id, snippet_set_id, url, created_at)
SELECT UNHEX(REPLACE(UUID(),'-','')), d.sales_channel_id, d.language_id, d.currency_id, d.snippet_set_id, '${SANDBOX_URL}', NOW(3)
FROM sales_channel_domain d
JOIN sales_channel sc ON sc.id = d.sales_channel_id
WHERE sc.type_id = UNHEX('8a243080f92e4c719546314b577cf82b')
  AND NOT EXISTS (SELECT 1 FROM sales_channel_domain e WHERE e.url = '${SANDBOX_URL}')
LIMIT 1;
SQL

echo "sales-channel domains now:"; my -N -e "SELECT url FROM sales_channel_domain;"

# The raw SQL insert bypasses DAL's cache invalidation, so clear the cached domain->sales-channel
# map; otherwise a storefront request already warmed pre-insert would keep 400-ing.
APP_ENV=prod php bin/console cache:pool:clear --all || APP_ENV=prod php bin/console cache:clear || true
