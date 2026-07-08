#!/usr/bin/env bash
# Sandbox probe — the ONLY command the probe agent runs, executed INSIDE the gh-aw sandbox.
#
# Why this exists: the sandbox only wraps the agent step, so the June host-side preflight passed
# while the real sandboxed run hit walls. This script measures every host contact point the
# reproduce agent will depend on FROM INSIDE the box, so we know which walls are real before flipping
# the sandbox on for reproduce.md. See dev/sandbox-handoff.md §3 (wall inventory) and §4a.
#
# Contract:
#   * ALWAYS exits 0. A non-zero exit is the #1 trigger for a cheap agent to "helpfully" debug; the
#     trusted HOST post-step owns pass/fail. Every check is bounded and self-reports; nothing aborts.
#   * Reports over TWO channels so we can tell "script ran, handoff broke" from "script never ran":
#       1. stdout, between PROBE-RESULT-BEGIN / PROBE-RESULT-END — lands in agent-stdio.log on the
#          host even when the workspace bind-mount does NOT propagate files back.
#       2. $GITHUB_WORKSPACE/sandbox-probe-report.json — the file's ARRIVAL on the host IS the
#          wall-#7 (workspace handoff) test.
#
# Check rows carry {wall, check, ok, detail}. `ok` means "matches the expectation for a healthy
# sandbox" — for php/mysql the expectation is ABSENT, so absent scores ok=true.

# Never abort: we self-report failures instead of letting set -e kill the run.
set +e +o histexpand 2>/dev/null

WORKSPACE="${GITHUB_WORKSPACE:-$PWD}"
REPORT="${WORKSPACE}/sandbox-probe-report.json"
CLI="${WORKSPACE}/.github/actions/reproduce/cli/repro.mjs"

CHECKS=""     # comma-joined JSON objects
TOTAL=0
FAILED=0

# json-escape a string into a bash-safe fragment (quotes, backslashes, control chars)
_json_escape() {
  local s="$1"
  s=${s//\\/\\\\}
  s=${s//\"/\\\"}
  s=${s//$'\t'/ }
  s=${s//$'\r'/ }
  s=${s//$'\n'/ }
  printf '%s' "$s"
}

# add <wall> <check> <ok:true|false> <detail...>
add() {
  local wall="$1" check="$2" ok="$3"; shift 3
  local detail; detail="$(_json_escape "$*")"
  TOTAL=$((TOTAL + 1))
  [ "$ok" = "true" ] || FAILED=$((FAILED + 1))
  local row="{\"wall\":\"${wall}\",\"check\":\"${check}\",\"ok\":${ok},\"detail\":\"${detail}\"}"
  if [ -z "$CHECKS" ]; then CHECKS="$row"; else CHECKS="${CHECKS},${row}"; fi
  printf '  [%-3s] %-30s ok=%-5s  %s\n' "$wall" "$check" "$ok" "$*"
}

have() { command -v "$1" >/dev/null 2>&1; }

# HTTP-result classifiers. Kept as functions (NOT inline `case` in $()) because a `case` pattern's
# `)` breaks command substitution parsing.
cls_reach()   { case "$1" in "HTTP 2"*|"HTTP 3"*|"HTTP 401") echo true;; *) echo false;; esac; }  # reachable/authy
cls_2xx()     { case "$1" in "HTTP 2"*) echo true;; *) echo false;; esac; }
cls_2xx3xx()  { case "$1" in "HTTP 2"*|"HTTP 3"*) echo true;; *) echo false;; esac; }

# curl a URL, return "HTTP <code>" or "ERR <exit>"; bounded so a hung host never stalls the probe.
http_probe() {
  local url="$1" extra="${2:-}" out code
  if ! have curl; then printf 'no-curl'; return; fi
  # shellcheck disable=SC2086
  code=$(timeout 12 curl -sS -k -m 8 -o /dev/null -w '%{http_code}' $extra "$url" 2>/dev/null)
  local rc=$?
  if [ "$rc" -ne 0 ]; then printf 'ERR rc=%s' "$rc"; else printf 'HTTP %s' "$code"; fi
}

echo "PROBE-RESULT-BEGIN"
echo "sandbox-probe: measuring in-sandbox host contact points"

# ---------------------------------------------------------------------------
# Identity / environment
# ---------------------------------------------------------------------------
add env whoami true "uid=$(id -u 2>/dev/null) user=$(id -un 2>/dev/null || echo '?')"
add env pwd true "$(pwd)"
add env kernel true "$(uname -srm 2>/dev/null)"
add env workspace_writable "$( [ -w "$WORKSPACE" ] && echo true || echo false )" "GITHUB_WORKSPACE=$WORKSPACE"
add env app_url_present "$( [ -n "${APP_URL:-}" ] && echo true || echo false )" "APP_URL=${APP_URL:-<unset>}"
add env access_key_present "$( [ -n "${SW_ACCESS_KEY:-}" ] && echo true || echo false )" "SW_ACCESS_KEY=$( [ -n "${SW_ACCESS_KEY:-}" ] && echo '<set>' || echo '<unset>')"
add env path true "PATH=${PATH}"

# ---------------------------------------------------------------------------
# Wall #1 — reproduce CLI usable from the workspace checkout in-container
# ---------------------------------------------------------------------------
add "#1" host_tmp_reproduce "$( [ -d /tmp/reproduce ] && echo true || echo false )" \
  "$( [ -d /tmp/reproduce ] && echo 'host immutable copy visible' || echo 'host /tmp/reproduce NOT visible in container' )"
add "#1" repro_shim_on_path "$( have repro && echo true || echo false )" \
  "$(command -v repro 2>/dev/null || echo 'repro shim not on PATH')"
add "#1" node_present "$( have node && echo true || echo false )" "$(node --version 2>/dev/null || echo 'node missing')"
if have node && [ -f "$CLI" ]; then
  out=$(timeout 30 node "$CLI" 2>&1)   # no args → prints the command list to stderr, exits 2
  if printf '%s' "$out" | grep -q 'commands:'; then
    add "#1" workspace_cli_resolves true "node repro.mjs resolved its imports (usage printed)"
  else
    add "#1" workspace_cli_resolves false "node repro.mjs did not print usage: $(printf '%s' "$out" | head -c 200)"
  fi
else
  add "#1" workspace_cli_resolves false "node or ${CLI} missing"
fi

# ---------------------------------------------------------------------------
# Wall #2 — reach the provisioned shop; which host+port combos does the firewall pass?
#   localhost:8000              → container's own localhost (expected: unreachable)
#   host.docker.internal:8000   → real shop, raw port NOT in the awf allowlist (expected: blocked)
#   host.docker.internal:8080   → same real shop via the probe forwarder on an ALLOWED port
#                                 (expected: reachable if host-access works)
# The 8000-vs-8080 split isolates "firewall blocks the shop's raw port" from "host-access broken".
# ---------------------------------------------------------------------------
r="$(http_probe 'http://localhost:8000/admin')"
add "#2" localhost_8000_admin "$(cls_reach "$r")" "$r"
r="$(http_probe 'http://host.docker.internal:8000/admin')"
add "#2" hostdocker_8000_admin "$(cls_reach "$r")" "$r (shop's raw port, unlisted)"
r="$(http_probe 'http://host.docker.internal:8080/admin')"
shop_reachable="$(cls_reach "$r")"
add "#2" hostdocker_8080_admin "$shop_reachable" "$r (shop via allowed-port forwarder)"

# ---------------------------------------------------------------------------
# Wall #3 — Shopware sales-channel domain routing: does the shop accept the sandbox Host header?
# Tested against the allowed-port forwarder (Host: host.docker.internal:8080). A 404/redirect on the
# storefront here is the REAL wall-#3 signal — the sandbox host is not a registered domain yet.
# ---------------------------------------------------------------------------
if [ "$shop_reachable" = true ]; then
  base="http://host.docker.internal:8080"
  r="$(http_probe "${base}/")"
  add "#3" storefront_home "$(cls_2xx3xx "$r")" "storefront / with Host host.docker.internal:8080: $r"
  if [ -n "${SW_ACCESS_KEY:-}" ]; then
    r="$(http_probe "${base}/store-api/context" "-H sw-access-key:${SW_ACCESS_KEY}")"
    add "#3" store_api_context "$(cls_2xx "$r")" "/store-api/context: $r"
  else
    add "#3" store_api_context false "skipped — SW_ACCESS_KEY unset"
  fi
  r="$(http_probe "${base}/api/_info/version")"
  add "#3" admin_api_reachable "$(cls_reach "$r")" "/api/_info/version (401 expected without auth): $r"
else
  add "#3" storefront_home false "gated: shop unreachable via the allowed-port forwarder (see wall #2)"
  add "#3" store_api_context false "gated: shop unreachable (see wall #2)"
fi

# ---------------------------------------------------------------------------
# Wall #4 — Playwright browsers usable inside the sandbox?
# ---------------------------------------------------------------------------
add "#4" playwright_cli_present "$( have playwright-cli && echo true || echo false )" \
  "$(command -v playwright-cli 2>/dev/null || echo 'playwright-cli not on PATH in-container')"
add "#4" browsers_path_env "$( [ -n "${PLAYWRIGHT_BROWSERS_PATH:-}" ] && echo true || echo false )" \
  "PLAYWRIGHT_BROWSERS_PATH=${PLAYWRIGHT_BROWSERS_PATH:-<unset>}"
ms_cache="${HOME}/.cache/ms-playwright"
add "#4" ms_playwright_cache "$( [ -d "$ms_cache" ] && echo true || echo false )" \
  "$( [ -d "$ms_cache" ] && echo "$ms_cache ($(ls -1 "$ms_cache" 2>/dev/null | tr '\n' ' '))" || echo "$ms_cache absent in-container" )"
if have playwright-cli; then
  out=$(timeout 30 playwright-cli --version 2>&1); rc=$?
  add "#4" playwright_cli_runs "$( [ "$rc" -eq 0 ] && echo true || echo false )" "$(printf '%s' "$out" | head -c 160)"
else
  add "#4" playwright_cli_runs false "playwright-cli absent"
fi

# ---------------------------------------------------------------------------
# Walls #5 / #6 — php / mysql are EXPECTED ABSENT in the agent container (host-only tools).
# Present is informational (would enable option A of the handoff); absent scores ok=true.
# ---------------------------------------------------------------------------
for bin in php mysql composer symfony; do
  if have "$bin"; then
    add "#5/6" "present_${bin}" true "PRESENT: $(command -v "$bin") ($("$bin" --version 2>/dev/null | head -c 60))"
  else
    add "#5/6" "absent_${bin}" true "absent (expected for the agent container)"
  fi
done

# ---------------------------------------------------------------------------
# Negative controls — a probe that cannot fail these proves nothing.
# ---------------------------------------------------------------------------
# Firewall on: an unlisted domain MUST be blocked. ok=true means the request FAILED as intended.
r="$(http_probe 'https://example.com/')"
case "$r" in
  HTTP\ 2*|HTTP\ 3*) add neg firewall_blocks_unlisted false "example.com reachable ($r) — firewall NOT enforcing" ;;
  *)                  add neg firewall_blocks_unlisted true  "example.com blocked ($r) — firewall enforcing" ;;
esac
# Immutability: writing into the host's read-only CLI copy MUST fail (only meaningful if it's visible).
if [ -d /tmp/reproduce ]; then
  if ( : > /tmp/reproduce/.probe-writetest ) 2>/dev/null; then
    rm -f /tmp/reproduce/.probe-writetest 2>/dev/null
    add neg immutable_cli_readonly false "/tmp/reproduce is WRITABLE from the agent — immutability not enforced"
  else
    add neg immutable_cli_readonly true "/tmp/reproduce is read-only from the agent (expected)"
  fi
else
  add neg immutable_cli_readonly true "n/a — /tmp/reproduce not visible in container"
fi

# ---------------------------------------------------------------------------
# Assemble the report (manual JSON; jq is not guaranteed in the agent image).
# ---------------------------------------------------------------------------
REPORT_JSON="{\"schema\":\"sandbox-probe/1\",\"generated_in\":\"sandbox-agent\",\"summary\":{\"total\":${TOTAL},\"failed\":${FAILED}},\"checks\":[${CHECKS}]}"

echo ""
echo "sandbox-probe: ${FAILED} of ${TOTAL} checks failed the healthy-sandbox expectation"
echo "PROBE-JSON: ${REPORT_JSON}"
echo "PROBE-RESULT-END"

# Channel 2: the workspace file whose arrival on the host is the wall-#7 test.
mkdir -p "$(dirname "$REPORT")" 2>/dev/null
if printf '%s\n' "$REPORT_JSON" > "$REPORT" 2>/dev/null; then
  echo "wrote ${REPORT}"
else
  echo "::warning::could not write ${REPORT} — workspace not writable from the agent"
fi

exit 0
