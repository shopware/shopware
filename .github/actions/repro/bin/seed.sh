#!/usr/bin/env bash
# Seed exactly the entities a repro needs, via the admin sync API. NO demodata.
#
# Reads the plan's sync payload (entities the Analyze agent derived), resolves the
# install-specific placeholders ({{SC}}/{{NAV_CAT}}/{{TAX}}/{{CURRENCY}}) against the
# running shop, and POSTs /api/_action/sync. Idempotent upsert.
#
# Env:
#   APP_URL     base URL of the running shop          (required)
#   PAYLOAD     path to the sync payload JSON          (default: fixtures.json)
#   ADMIN_USER  admin username (default-install: admin)
#   ADMIN_PASS  admin password (default-install: shopware)
set -euo pipefail

: "${APP_URL:?APP_URL is required}"
PAYLOAD="${PAYLOAD:-fixtures.json}"
BASE=${APP_URL%/}
USER="${ADMIN_USER:-admin}"
PASS="${ADMIN_PASS:-shopware}"

# A plan with no fixtures is valid (e.g. demodata:false + the bug needs no seed data).
if [ ! -f "$PAYLOAD" ]; then
  echo "no fixtures payload ($PAYLOAD) — nothing to seed"
  exit 0
fi

# The sync API requires an OPERATION envelope per key: {entity, action, payload:[...]}.
# Agents sometimes emit the bare shape {"product": [ {...} ]} instead (a real 400 we hit:
# FRAMEWORK__INVALID_SYNC_OPERATION). Auto-wrap bare entity→array keys into upserts.
WRAPPED=$(mktemp)
jq 'with_entries(if (.value|type) == "array"
      then .value = {entity: .key, action: "upsert", payload: .value}
      else . end)' "$PAYLOAD" > "$WRAPPED" || { echo "::error::fixtures payload is not valid JSON"; exit 1; }
PAYLOAD="$WRAPPED"

# `customer` requires a non-blank `customerNumber` (no server-side default), which
# hand-assembled fixtures routinely omit → sync 400 "This value should not be blank"
# at /customer/0/customerNumber (hit live on #33). Auto-fill it deterministically from
# the row id for any customer operation that lacks one — this is harness boilerplate,
# not repro logic, same class as resolving the {{…}} placeholders below.
ENRICHED=$(mktemp)
jq 'with_entries(if (.value.entity? == "customer")
      then .value.payload |= map(if ((.customerNumber // "") == "")
            then . + {customerNumber: ("RPR-" + ((.id // "x") | tostring)[0:12])} else . end)
      else . end)' "$PAYLOAD" > "$ENRICHED" && PAYLOAD="$ENRICHED"

# 1. Admin token via the first-party password grant (works on a default install).
TOKEN=$(curl -sS --max-time 30 -X POST "$BASE/api/oauth/token" \
  -H 'Content-Type: application/json' \
  -d "{\"grant_type\":\"password\",\"client_id\":\"administration\",\"username\":\"$USER\",\"password\":\"$PASS\",\"scopes\":\"write\"}" \
  | jq -r '.access_token // empty')
[ -n "$TOKEN" ] || { echo "::error::admin token request failed"; exit 1; }
# Accept: application/json → flat response (id + navigationCategoryId at top level);
# without it /api/search returns JSON:API where nested fields live under .attributes.
AUTH=(-H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json')

# 2. Resolve install-specific ids referenced by the payload as placeholders.
# NOTE: keep this set in sync with run-http.sh's resolver (the two diverged once).
search () { curl -sS --max-time 30 -X POST "$BASE/api/search/$1" "${AUTH[@]}" -d "$2"; }
# Resolve the sales channel the store-api executor authenticates as (SC_ID, passed by
# provision) so the product is seeded visible in THAT channel; else fall back to the oldest
# active SC (createdAt ASC matches provision's `ORDER BY created_at`, keeping the two in sync).
if [ -n "${SC_ID:-}" ]; then
  SC_JSON=$(search sales-channel '{"limit":1,"filter":[{"type":"equals","field":"id","value":"'"$SC_ID"'"}]}')
else
  SC_JSON=$(search sales-channel '{"limit":1,"filter":[{"type":"equals","field":"active","value":true}],"sort":[{"field":"createdAt","order":"ASC"}]}')
fi
SC=$(echo "$SC_JSON"  | jq -r '.data[0].id // empty')
NAV=$(echo "$SC_JSON" | jq -r '.data[0].navigationCategoryId // empty')
TAX=$(search tax '{"limit":1}'      | jq -r '.data[0].id // empty')
CUR=$(search currency '{"limit":1,"filter":[{"type":"equals","field":"isoCode","value":"EUR"}]}' | jq -r '.data[0].id // empty')
COUNTRY=$(search country '{"limit":1,"filter":[{"type":"equals","field":"active","value":true}]}' | jq -r '.data[0].id // empty')
SALS=$(search salutation '{"limit":2}')
SAL=$(echo "$SALS"  | jq -r '.data[0].id // empty')
SAL2=$(echo "$SALS" | jq -r '.data[1].id // .data[0].id // empty')
LANG=$(search language '{"limit":1}' | jq -r '.data[0].id // empty')

# Fail loud if a referenced placeholder resolved to EMPTY (else we'd POST an empty UUID).
for kv in "SC:$SC" "NAV_CAT:$NAV" "TAX:$TAX" "CURRENCY:$CUR" "COUNTRY:$COUNTRY" "SALUTATION:$SAL" "SALUTATION2:$SAL2" "LANGUAGE:$LANG"; do
  k=${kv%%:*}; v=${kv#*:}
  if grep -q "{{$k}}" "$PAYLOAD" && [ -z "$v" ]; then
    echo "::error::could not resolve {{$k}} (admin search returned empty)"; exit 1
  fi
done

OUT=$(mktemp)
sed -e "s/{{SC}}/$SC/g" -e "s/{{NAV_CAT}}/$NAV/g" -e "s/{{TAX}}/$TAX/g" -e "s/{{CURRENCY}}/$CUR/g" \
    -e "s/{{COUNTRY}}/$COUNTRY/g" -e "s/{{SALUTATION2}}/$SAL2/g" -e "s/{{SALUTATION}}/$SAL/g" -e "s/{{LANGUAGE}}/$LANG/g" "$PAYLOAD" > "$OUT"

# Fail loud if any placeholder is still unresolved (would seed broken entities).
if grep -q '{{' "$OUT"; then
  echo "::error::unresolved placeholder(s) in sync payload:"; grep -o '{{[^}]*}}' "$OUT" | sort -u
  exit 1
fi

# 3. Upsert the entities.
RESP=$(mktemp)
CODE=$(curl -sS --max-time 60 -o "$RESP" -w '%{http_code}' -X POST "$BASE/api/_action/sync" "${AUTH[@]}" --data @"$OUT")
if [ "$CODE" != "200" ] && [ "$CODE" != "204" ]; then
  echo "::error::sync failed (HTTP $CODE)"; cat "$RESP"
  # Persist the API's validation detail so leg-blocked.sh can surface it in the report —
  # a buried 400 made repeated invalid-fixture rolls undiagnosable from the issue side.
  { printf 'sync HTTP %s: ' "$CODE"; jq -r '[.errors[]?.detail] | join("; ")' "$RESP" 2>/dev/null || head -c 300 "$RESP"; } \
    | head -c 400 > seed-error.txt
  exit 1
fi
echo "seeded OK (sync HTTP $CODE; SC=$SC nav=$NAV tax=$TAX cur=$CUR)"

# 4. Apply any system_config the plan declares (analysis.json `.system_config`: a flat
#    {key:value} map). Needed when a bug sits behind an OPT-IN feature that is OFF by default —
#    e.g. core.cart.wishlistEnabled is disabled by default (Migration…TemporarilyDisableWishlist),
#    so a guest-wishlist repro's storefront pages stay dead until it is turned on. Use the admin
#    batch route (it handles the {"_value":…} serialization the raw sync does not); set both
#    global ("null") and the seeded SC. A declared-but-unappliable setting = a dead env → fail loud.
CFG=$(jq -c '.system_config // {}' "${ANALYSIS:-analysis.json}" 2>/dev/null || echo '{}')
if [ -n "$CFG" ] && [ "$CFG" != '{}' ] && [ "$CFG" != 'null' ]; then
  BATCH=$(jq -cn --argjson cfg "$CFG" --arg sc "$SC" '{"null":$cfg} + (if $sc=="" then {} else {($sc):$cfg} end)')
  CCODE=$(curl -sS --max-time 30 -o "$RESP" -w '%{http_code}' -X POST "$BASE/api/_action/system-config/batch" "${AUTH[@]}" --data "$BATCH")
  if [ "$CCODE" != "200" ] && [ "$CCODE" != "204" ]; then
    echo "::error::system-config batch failed (HTTP $CCODE)"; cat "$RESP"
    { printf 'system-config HTTP %s: ' "$CCODE"; head -c 300 "$RESP"; } > seed-error.txt; exit 1
  fi
  echo "system_config applied ($CFG)"
fi
