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
# Each check row is {wall, check, required, ok, detail}:
#   * required=true  → a real gate. If ok=false the sandbox is NOT ready; the run goes red.
#   * required=false → informational. Recorded for humans, but NEVER makes the run red. Used for
#     observations that are expected-negative (localhost inside the sandbox is not the host) or
#     benign (a missing PATH shim when the workspace CLI works; php/mysql presence is a bonus).
#   `ok` means "matches the expectation for a healthy sandbox".

# Never abort: we self-report failures instead of letting set -e kill the run.
set +e +o histexpand 2>/dev/null

WORKSPACE="${GITHUB_WORKSPACE:-$PWD}"
REPORT="${WORKSPACE}/sandbox-probe-report.json"
CLI="${WORKSPACE}/.github/actions/reproduce/cli/repro.mjs"

CHECKS=""     # comma-joined JSON objects
TOTAL=0
FAILED=0      # counts ONLY required checks that are not ok

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

# add <wall> <check> <req|info> <ok:true|false> <detail...>
add() {
  local wall="$1" check="$2" sev="$3" ok="$4"; shift 4
  local detail; detail="$(_json_escape "$*")"
  local required=true; [ "$sev" = info ] && required=false
  TOTAL=$((TOTAL + 1))
  # Only required, not-ok checks count as failures / turn the run red.
  if [ "$required" = true ] && [ "$ok" != true ]; then FAILED=$((FAILED + 1)); fi
  local row="{\"wall\":\"${wall}\",\"check\":\"${check}\",\"required\":${required},\"ok\":${ok},\"detail\":\"${detail}\"}"
  if [ -z "$CHECKS" ]; then CHECKS="$row"; else CHECKS="${CHECKS},${row}"; fi
  local mark; if [ "$required" = true ]; then mark=$([ "$ok" = true ] && echo "PASS" || echo "FAIL"); else mark="info"; fi
  printf '  [%-3s] %-30s %-5s ok=%-5s  %s\n' "$wall" "$check" "$mark" "$ok" "$*"
}

have() { command -v "$1" >/dev/null 2>&1; }

# HTTP-result classifiers. Kept as functions (NOT inline `case` in $()) because a `case` pattern's
# `)` breaks command substitution parsing.
cls_reach()   { case "$1" in "HTTP 2"*|"HTTP 3"*|"HTTP 401") echo true;; *) echo false;; esac; }  # reachable/authy
cls_2xx()     { case "$1" in "HTTP 2"*) echo true;; *) echo false;; esac; }
cls_2xx3xx()  { case "$1" in "HTTP 2"*|"HTTP 3"*) echo true;; *) echo false;; esac; }

# curl a URL, return "HTTP <code>" or "ERR <exit>"; bounded so a hung host never stalls the probe.
http_probe() {
  local url="$1" extra="${2:-}" code
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
add env whoami            info true "uid=$(id -u 2>/dev/null) user=$(id -un 2>/dev/null || echo '?')"
add env pwd               info true "$(pwd)"
add env kernel            info true "$(uname -srm 2>/dev/null)"
add env workspace_writable req  "$( [ -w "$WORKSPACE" ] && echo true || echo false )" "GITHUB_WORKSPACE=$WORKSPACE"
add env app_url_present    req  "$( [ -n "${APP_URL:-}" ] && echo true || echo false )" "APP_URL=${APP_URL:-<unset>}"
add env access_key_present req  "$( [ -n "${SW_ACCESS_KEY:-}" ] && echo true || echo false )" "SW_ACCESS_KEY=$( [ -n "${SW_ACCESS_KEY:-}" ] && echo '<set>' || echo '<unset>')"
add env path              info true "PATH=${PATH}"

# ---------------------------------------------------------------------------
# Wall #1 — reproduce CLI usable from the workspace checkout in-container
# ---------------------------------------------------------------------------
add "#1" host_tmp_reproduce info "$( [ -d /tmp/reproduce ] && echo true || echo false )" \
  "$( [ -d /tmp/reproduce ] && echo 'host immutable copy visible' || echo 'host /tmp/reproduce NOT visible in container' )"
# Informational: the PATH shim is a convenience; the required path is the workspace CLI below.
add "#1" repro_shim_on_path info "$( have repro && echo true || echo false )" \
  "$(command -v repro 2>/dev/null || echo 'repro shim not on PATH — use node <workspace>/cli/repro.mjs')"
add "#1" node_present req "$( have node && echo true || echo false )" "$(node --version 2>/dev/null || echo 'node missing')"
if have node && [ -f "$CLI" ]; then
  out=$(timeout 30 node "$CLI" 2>&1)   # no args → prints the command list to stderr, exits 2
  if printf '%s' "$out" | grep -q 'commands:'; then
    add "#1" workspace_cli_resolves req true "node repro.mjs resolved its imports (usage printed)"
  else
    add "#1" workspace_cli_resolves req false "node repro.mjs did not print usage: $(printf '%s' "$out" | head -c 200)"
  fi
else
  add "#1" workspace_cli_resolves req false "node or ${CLI} missing"
fi

# ---------------------------------------------------------------------------
# Wall #2 — reach the provisioned shop; which host+port combos does the firewall pass?
#   localhost:8000            → the sandbox's OWN localhost, not the runner host (expected: refused;
#                               informational — it documents that localhost is not a usable path).
#   host.docker.internal:8000 → the real shop. REQUIRED. Blocked until compile.sh [P1] adds 8000 to
#                               awf --allow-host-ports; then this should return 200/302/401.
#   host.docker.internal:8080 → reserved by gh-aw's MCP gateway (informational sanity check).
# ---------------------------------------------------------------------------
r="$(http_probe 'http://localhost:8000/admin')"
add "#2" localhost_8000_admin info "$(cls_reach "$r")" "$r (sandbox-local, not the host — expected unreachable)"
r="$(http_probe 'http://host.docker.internal:8000/admin')"
shop_reachable="$(cls_reach "$r")"
add "#2" hostdocker_8000_admin req "$shop_reachable" "$r (shop on host port 8000)"
r="$(http_probe 'http://host.docker.internal:8080/')"
add "#2" port_8080_reserved info true "8080 (reserved by gh-aw MCP gateway) responds: $r"

# ---------------------------------------------------------------------------
# Wall #3 — Shopware sales-channel domain routing: does the shop accept the sandbox Host header?
# Only meaningful once the shop is reachable (wall #2). Storefront (/) is domain-routed, so a
# 404/redirect here means host.docker.internal:8000 is not a registered sales-channel domain yet —
# the real wall-#3 signal. store-api (access-key based) and admin API (host-agnostic) usually pass.
# ---------------------------------------------------------------------------
if [ "$shop_reachable" = true ]; then
  base="http://host.docker.internal:8000"
  r="$(http_probe "${base}/")"
  add "#3" storefront_home req "$(cls_2xx3xx "$r")" "storefront / with sandbox Host header: $r"
  if [ -n "${SW_ACCESS_KEY:-}" ]; then
    r="$(http_probe "${base}/store-api/context" "-H sw-access-key:${SW_ACCESS_KEY}")"
    add "#3" store_api_context req "$(cls_2xx "$r")" "/store-api/context: $r"
  else
    add "#3" store_api_context req false "skipped — SW_ACCESS_KEY unset"
  fi
  r="$(http_probe "${base}/api/_info/version")"
  add "#3" admin_api_reachable req "$(cls_reach "$r")" "/api/_info/version (401 expected without auth): $r"
else
  add "#3" storefront_home req false "gated: shop unreachable (see wall #2 — needs compile.sh [P1])"
  add "#3" store_api_context req false "gated: shop unreachable (see wall #2)"
  add "#3" admin_api_reachable req false "gated: shop unreachable (see wall #2)"
fi

# ---------------------------------------------------------------------------
# Wall #4 — Playwright browsers usable inside the sandbox?
# ---------------------------------------------------------------------------
add "#4" playwright_cli_present req "$( have playwright-cli && echo true || echo false )" \
  "$(command -v playwright-cli 2>/dev/null || echo 'playwright-cli not on PATH in-container')"
# Informational: the env var need not be set as long as the default cache resolves (checked next).
add "#4" browsers_path_env info "$( [ -n "${PLAYWRIGHT_BROWSERS_PATH:-}" ] && echo true || echo false )" \
  "PLAYWRIGHT_BROWSERS_PATH=${PLAYWRIGHT_BROWSERS_PATH:-<unset>}"
ms_cache="${HOME}/.cache/ms-playwright"
add "#4" ms_playwright_cache req "$( [ -d "$ms_cache" ] && echo true || echo false )" \
  "$( [ -d "$ms_cache" ] && echo "$ms_cache ($(ls -1 "$ms_cache" 2>/dev/null | tr '\n' ' '))" || echo "$ms_cache absent in-container" )"
if have playwright-cli; then
  out=$(timeout 30 playwright-cli --version 2>&1); rc=$?
  add "#4" playwright_cli_runs req "$( [ "$rc" -eq 0 ] && echo true || echo false )" "$(printf '%s' "$out" | head -c 160)"
else
  add "#4" playwright_cli_runs req false "playwright-cli absent"
fi

# ---------------------------------------------------------------------------
# Walls #5 / #6 — php / mysql inside the agent environment. On this runner the sandbox is
# host-chroot + firewall (not a clean container), so they are PRESENT — a bonus that keeps direct/
# phpunit `repro try` + demodata viable. Informational either way (absence would not block the CLI's
# HTTP-based seeding, presence is a plus).
# ---------------------------------------------------------------------------
for bin in php mysql composer symfony; do
  if have "$bin"; then
    add "#5/6" "have_${bin}" info true "PRESENT: $(command -v "$bin") ($("$bin" --version 2>/dev/null | head -c 60))"
  else
    add "#5/6" "have_${bin}" info true "absent"
  fi
done

# ---------------------------------------------------------------------------
# Negative controls — REQUIRED. A probe that cannot fail these proves nothing.
# ---------------------------------------------------------------------------
# Firewall on: an unlisted domain MUST be blocked. ok=true means the request FAILED as intended.
r="$(http_probe 'https://example.com/')"
case "$r" in
  HTTP\ 2*|HTTP\ 3*) add neg firewall_blocks_unlisted req false "example.com reachable ($r) — firewall NOT enforcing" ;;
  *)                 add neg firewall_blocks_unlisted req true  "example.com blocked ($r) — firewall enforcing" ;;
esac
# Immutability: writing into the host's read-only CLI copy MUST fail (only meaningful if it's visible).
if [ -d /tmp/reproduce ]; then
  if ( : > /tmp/reproduce/.probe-writetest ) 2>/dev/null; then
    rm -f /tmp/reproduce/.probe-writetest 2>/dev/null
    add neg immutable_cli_readonly req false "/tmp/reproduce is WRITABLE from the agent — immutability not enforced"
  else
    add neg immutable_cli_readonly req true "/tmp/reproduce is read-only from the agent (expected)"
  fi
else
  add neg immutable_cli_readonly info true "n/a — /tmp/reproduce not visible in container"
fi

# ---------------------------------------------------------------------------
# Assemble the report (manual JSON; jq is not guaranteed in the agent image).
# `failed` counts REQUIRED checks only — informational rows never make the run red.
# ---------------------------------------------------------------------------
REPORT_JSON="{\"schema\":\"sandbox-probe/2\",\"generated_in\":\"sandbox-agent\",\"summary\":{\"total\":${TOTAL},\"failed\":${FAILED}},\"checks\":[${CHECKS}]}"

echo ""
echo "sandbox-probe: ${FAILED} REQUIRED checks failed (informational rows excluded)"
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
