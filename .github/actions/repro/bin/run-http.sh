#!/usr/bin/env bash
# `http` executor (v2). Reads the plan (analysis.json), runs the request — or a
# request SEQUENCE — against the running shop, and asserts on the FINAL response.
#
# Supports:
#  - auth by surface: admin API (/api/...) → admin OAuth Bearer; store API (/store-api/...)
#    → sw-access-key. Chosen per request from its path; the executor owns auth (any auth
#    header the agent wrote is dropped), so an admin-api request is never sent a store key.
#  - install-specific placeholders in path/body/headers, resolved against the shop:
#    {{SC}} {{NAV_CAT}} {{COUNTRY}} {{SALUTATION}} {{SALUTATION2}} {{TAX}} {{CURRENCY}}
#    {{LANGUAGE}} {{STOREFRONT_URL}} {{SW_ACCESS_KEY}} {{SW_CONTEXT_TOKEN}}
#  - multi-step: `requests: [...]`; sw-context-token is captured and carried forward;
#    a non-final setup request that isn't 2xx => blocked.
#  - false-positive guards: a 401/403 (auth rejected before the symptom ran), or an
#    unparseable/empty response_field on a non-2xx response => inconclusive (NOT a bogus
#    "reproduced").
#
# expect = HEALTHY value: actual != expect => reproduced; actual == expect => not_reproduced.
#
# Env: ANALYSIS, OUT, APP_URL (req), TARGET (req), SW_ACCESS_KEY, ADMIN_USER, ADMIN_PASS
set -euo pipefail

ANALYSIS=${ANALYSIS:-analysis.json}
OUT=${OUT:-result.json}
: "${APP_URL:?APP_URL is required}"
: "${TARGET:?TARGET is required}"
BASE=${APP_URL%/}
ACCESS_KEY=${SW_ACCESS_KEY:-}
ADMIN_USER=${ADMIN_USER:-admin}
ADMIN_PASS=${ADMIN_PASS:-shopware}

VERSION=$(jq -r '.version // "unknown"' "$ANALYSIS")
KIND=$(jq -r '.assertion.kind' "$ANALYSIS")
EXPECT=$(jq -r '.assertion.expect | tostring' "$ANALYSIS")
FIELD=$(jq -r '.assertion.field // ""' "$ANALYSIS")
# A single `request` is treated as a one-element sequence.
REQS=$(jq -c 'if .requests then .requests else [.request] end' "$ANALYSIS")
NREQ=$(echo "$REQS" | jq 'length')

# Plain vars (no associative array → portable to bash 3.2 + the CI's bash 5).
SW_ACCESS_KEY_V="$ACCESS_KEY"; STOREFRONT_URL="$BASE"
SC=""; NAV_CAT=""; COUNTRY=""; SALUTATION=""; SALUTATION2=""; TAX=""; CURRENCY=""; LANGUAGE=""

# Auth by surface: the admin API (/api/...) needs an OAuth Bearer token; the store API
# (/store-api/...) uses sw-access-key. Detect whether ANY request targets the admin API.
is_admin_path() { case "$1" in /store-api/*) return 1 ;; /api/*) return 0 ;; *) return 1 ;; esac; }
ADMIN_REQ=0
while IFS= read -r p; do is_admin_path "$p" && { ADMIN_REQ=1; break; }; done < <(echo "$REQS" | jq -r '.[].path // ""')

# Resolve install-specific ids only if the plan references {{...}} beyond the free ones (admin API).
NEED=$(echo "$REQS" | grep -oE '\{\{[A-Z0-9_]+\}\}' | sort -u | tr -d '{}' || true)
NEED_IDS=0
echo "$NEED" | grep -qvE '^(SW_ACCESS_KEY|STOREFRONT_URL|SW_CONTEXT_TOKEN)?$' && NEED_IDS=1

# Fetch the admin OAuth token once if EITHER an admin-api request will run OR we must resolve
# install-specific ids (both go through the admin API). Without it, admin-api requests get the
# store-api key and the gateway returns 401 — a harness auth failure, not the reported bug.
TOKEN=""
if [ "$ADMIN_REQ" = 1 ] || [ "$NEED_IDS" = 1 ]; then
  TOKEN=$(curl -sS --max-time 30 -X POST "$BASE/api/oauth/token" -H 'Content-Type: application/json' \
    -d "{\"grant_type\":\"password\",\"client_id\":\"administration\",\"username\":\"$ADMIN_USER\",\"password\":\"$ADMIN_PASS\",\"scopes\":\"write\"}" \
    | jq -r '.access_token // empty')
  [ -n "$TOKEN" ] || { echo "::error::admin OAuth token request failed (needed for admin-api auth / id resolution)"; exit 1; }
fi

if [ "$NEED_IDS" = 1 ]; then
  A=(-H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json')
  q() { curl -sS --max-time 30 -X POST "$BASE/api/search/$1" "${A[@]}" -d "$2"; }
  SCJ=$(q sales-channel '{"limit":1,"filter":[{"type":"equals","field":"active","value":true}]}')
  SC=$(echo "$SCJ" | jq -r '.data[0].id // empty')
  NAV_CAT=$(echo "$SCJ" | jq -r '.data[0].navigationCategoryId // empty')
  # storefrontUrl must be a registered SC domain (a basic-setup default SC is headless,
  # domain "default.headlessN"), NOT APP_URL. Resolve the real domain when present.
  SCDOM=$(q sales-channel-domain '{"limit":1}' | jq -r '.data[0].url // empty')
  [ -n "$SCDOM" ] && STOREFRONT_URL="$SCDOM"
  COUNTRY=$(q country '{"limit":1,"filter":[{"type":"equals","field":"active","value":true}]}' | jq -r '.data[0].id // empty')
  SALS=$(q salutation '{"limit":2}')
  SALUTATION=$(echo "$SALS" | jq -r '.data[0].id // empty')
  SALUTATION2=$(echo "$SALS" | jq -r '.data[1].id // .data[0].id // empty')
  TAX=$(q tax '{"limit":1}' | jq -r '.data[0].id // empty')
  CURRENCY=$(q currency '{"limit":1,"filter":[{"type":"equals","field":"isoCode","value":"EUR"}]}' | jq -r '.data[0].id // empty')
  LANGUAGE=$(q language '{"limit":1}' | jq -r '.data[0].id // empty')
fi

CTX=""                       # sw-context-token, carried across the sequence
HEAD=$(mktemp); BODYF=$(mktemp); trap 'rm -f "$HEAD" "$BODYF"' EXIT
SCRIPT=""; CODE=""; blocked=""

resolve() { # substitute {{KEY}} placeholders (SALUTATION2 before SALUTATION)
  local s="$1"
  s="${s//\{\{SW_ACCESS_KEY\}\}/$SW_ACCESS_KEY_V}"
  s="${s//\{\{STOREFRONT_URL\}\}/$STOREFRONT_URL}"
  s="${s//\{\{SC\}\}/$SC}"
  s="${s//\{\{NAV_CAT\}\}/$NAV_CAT}"
  s="${s//\{\{COUNTRY\}\}/$COUNTRY}"
  s="${s//\{\{SALUTATION2\}\}/$SALUTATION2}"
  s="${s//\{\{SALUTATION\}\}/$SALUTATION}"
  s="${s//\{\{TAX\}\}/$TAX}"
  s="${s//\{\{CURRENCY\}\}/$CURRENCY}"
  s="${s//\{\{LANGUAGE\}\}/$LANGUAGE}"
  s="${s//\{\{SW_CONTEXT_TOKEN\}\}/$CTX}"
  printf '%s' "$s"
}

# assertion.expect may itself reference a resolved id (e.g. {{SALUTATION2}}).
EXPECT=$(resolve "$EXPECT")

for i in $(seq 0 $((NREQ - 1))); do
  R=$(echo "$REQS" | jq -c ".[$i]")
  M=$(echo "$R" | jq -r '.method // "GET"')
  P=$(resolve "$(echo "$R" | jq -r '.path // ""')")
  B=$(resolve "$(echo "$R" | jq -r '.body // ""')")
  CURL=(curl -sS --max-time 30 -o "$BODYF" -D "$HEAD" -w '%{http_code}' -X "$M" "$BASE$P")
  # Auth by surface: admin API → OAuth Bearer; store API → sw-access-key. The executor owns
  # auth (it dropped any auth header the agent wrote), so each request is authenticated for
  # the API it actually targets — an admin-api request no longer goes out with a store key.
  DISP_H=""
  if is_admin_path "$P"; then
    [ -n "$TOKEN" ] && { CURL+=(-H "Authorization: Bearer $TOKEN"); DISP_H=" -H \"Authorization: Bearer [REDACTED_TOKEN]\""; }
  else
    [ -n "$ACCESS_KEY" ] && { CURL+=(-H "sw-access-key: $ACCESS_KEY"); DISP_H=" -H \"sw-access-key: [REDACTED_KEY]\""; }
  fi
  [ -n "$CTX" ] && CURL+=(-H "sw-context-token: $CTX")
  # plan headers (resolved); drop any auth header the agent added (the executor injects the right one).
  has_ct=0
  while IFS= read -r h; do
    [ -n "$h" ] || continue
    case "$(printf '%s' "$h" | tr 'A-Z' 'a-z')" in sw-access-key:*|authorization:*) continue;; esac
    case "$(printf '%s' "$h" | tr 'A-Z' 'a-z')" in content-type:*) has_ct=1;; esac
    h=$(resolve "$h"); CURL+=(-H "$h"); DISP_H+=" -H \"$h\""
  done < <(echo "$R" | jq -r '.headers // {} | to_entries[] | "\(.key): \(.value)"')
  # store-api/admin-api are JSON: default Content-Type when a body is present and the plan
  # omitted it — otherwise curl sends form-encoded and every field reads as blank (400).
  if [ -n "$B" ] && [ "$has_ct" = 0 ]; then CURL+=(-H "Content-Type: application/json"); DISP_H+=" -H \"Content-Type: application/json\""; fi
  DISP_B=""; [ -n "$B" ] && { CURL+=(--data "$B"); DISP_B=" --data '$B'"; }
  SCRIPT="${SCRIPT}curl -sS -X $M \"\$APP_URL$P\"${DISP_H}${DISP_B}"$'\n'

  if ! CODE=$("${CURL[@]}" 2>/dev/null); then blocked="request $((i + 1)) ($M $P) — transport failure"; break; fi
  T=$(grep -i '^sw-context-token:' "$HEAD" 2>/dev/null | tail -1 | tr -d '\r' | sed 's/^[^:]*:[[:space:]]*//' || true)
  [ -n "$T" ] && CTX="$T"
  # a non-final SETUP request must succeed, else the repro can't proceed
  if [ "$i" -lt $((NREQ - 1)) ] && ! [[ "$CODE" =~ ^2 ]]; then
    blocked="setup request $((i + 1)) ($M $P) returned HTTP $CODE — $(head -c 1500 "$BODYF" 2>/dev/null | tr -d '\r\n' | tr -s ' ')"; break
  fi
done

if [ -n "$blocked" ]; then
  STATUS="blocked"; MATCHED="null"; ACTUAL="null"; REASON_TEXT="$blocked"; REPORTER="$blocked"
else
  REASON_TEXT=""
  case "$KIND" in
    http_status) ACTUAL_RAW="$CODE"; REPORTER="HTTP $CODE (expected $EXPECT)" ;;
    response_field)
      ACTUAL_RAW=$(jq -r "$FIELD" "$BODYF" 2>/dev/null || true); [ -n "$ACTUAL_RAW" ] || ACTUAL_RAW="<unparseable>"
      REPORTER="$FIELD = '$ACTUAL_RAW' (expected '$EXPECT'); HTTP $CODE" ;;
    *) ACTUAL_RAW="$CODE"; REPORTER="HTTP $CODE (unknown assertion kind '$KIND')" ;;
  esac
  # Guard: 401/403 means the gateway rejected auth BEFORE the business logic ran — the harness
  # credentials were rejected, not the reported symptom. Never score that "reproduced" (unless
  # the symptom genuinely IS an auth code) → inconclusive, surfaced to a human with the body.
  if { [ "$CODE" = "401" ] || [ "$CODE" = "403" ]; } && [ "$EXPECT" != "401" ] && [ "$EXPECT" != "403" ]; then
    STATUS="inconclusive"; MATCHED="null"; ACTUAL="\"$CODE\""
    REASON_TEXT="request returned HTTP $CODE (authentication/authorization rejected) before the symptom could be exercised — harness-credential failure, not the reported bug. body: $(head -c 500 "$BODYF" 2>/dev/null | tr -d '\r\n' | tr -s ' ')"
  # Guard: a missing field on a non-2xx response means the call was malformed/failed,
  # not that the symptom occurred → inconclusive, never a bogus "reproduced".
  elif [ "$KIND" = "response_field" ] && { [ "$ACTUAL_RAW" = "<unparseable>" ] || [ "$ACTUAL_RAW" = "null" ]; } && ! [[ "$CODE" =~ ^2 ]]; then
    STATUS="inconclusive"; MATCHED="null"; ACTUAL="\"$ACTUAL_RAW\""
    REASON_TEXT="final request returned HTTP $CODE, asserted field absent (likely malformed) — body: $(head -c 1500 "$BODYF" 2>/dev/null | tr -d '\r\n' | tr -s ' ')"
  elif [ "$ACTUAL_RAW" = "$EXPECT" ]; then
    STATUS="not_reproduced"; MATCHED="true"; ACTUAL="\"$ACTUAL_RAW\""
  else
    STATUS="reproduced"; MATCHED="false"; ACTUAL="\"$ACTUAL_RAW\""
  fi
fi

{ echo '#!/usr/bin/env bash'; echo '# Reproduction request(s) — set $APP_URL (executor injects auth: sw-access-key or admin Bearer, + sw-context-token).'; printf '%s' "$SCRIPT"; } > repro.sh

jq -n \
  --argjson issue "$(jq -r '.issue' "$ANALYSIS")" \
  --arg target "$TARGET" --arg version "$VERSION" --arg status "$STATUS" \
  --arg expect "$EXPECT" --argjson actual "$ACTUAL" --argjson matched "$MATCHED" \
  --arg script "$SCRIPT" --arg reporter "$REPORTER" --argjson code "${CODE:-0}" \
  --arg reason_text "$REASON_TEXT" '{
    schema_version: "1", issue: $issue, target: $target, version: $version, executor: "http",
    status: $status,
    assertion: { expect: $expect, actual: ($actual | if . == null then null else tostring end), matched: $matched },
    duration_s: 0,
    evidence: { script: $script, script_lang: "sh", reporter_output: $reporter,
      http: [{ status: $code }], artifacts: [], truncated: false },
    blocked_reason: (if $reason_text == "" then null else $reason_text end)
  }' > "$OUT"

echo "status=$STATUS  ($REPORTER)"
