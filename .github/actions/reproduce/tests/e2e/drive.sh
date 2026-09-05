#!/usr/bin/env bash
# End-to-end harness: drives the full reproduce pipeline against a LIVE shop (a local running instance
# or a CI-provisioned one) with a scripted fake agent in place of the LLM, all the way to a verdict +
# comment, and asserts the verdict matches the scenario's expected outcome.
#
# This exercises the "red field" the unit tests can't: real live-shop executors (http/direct/playwright),
# sandbox arming, and the executor → verify → verdict → comment spine. Both legs run against the one
# instance, so verdicts are live_bug / not_reproducible; the true two-version differential
# (fixed_on_trunk / regression) stays covered at the unit level in report/verdict.test.ts.
#
# Usage:
#   APP_URL=http://localhost:8000 SW_ACCESS_KEY=… [SHOP_DIR=… ADMIN_USER=… ADMIN_PASS=…] \
#     bash tests/e2e/drive.sh [--lockdown] <scenario…>
#
#   http scenarios need only APP_URL (+ SW_ACCESS_KEY for store-api). direct/playwright additionally
#   need docker and (direct) SHOP_DIR + a reachable DATABASE_URL.
set -uo pipefail

HERE=$(cd "$(dirname "$0")" && pwd)
ACTION=$(cd "$HERE/../.." && pwd)
STRIP="node --experimental-strip-types"
REPRO="$STRIP $ACTION/cli/repro.ts"

LOCKDOWN=0
SCENARIOS=()
for a in "$@"; do
  case "$a" in
    --lockdown) LOCKDOWN=1 ;;
    *) SCENARIOS+=("$a") ;;
  esac
done
[ "${#SCENARIOS[@]}" -gt 0 ] || { echo "usage: drive.sh [--lockdown] <scenario…>"; exit 2; }
: "${APP_URL:?APP_URL is required — point at a running shop (or a mock)}"

# Each scenario runs in its own mktemp cwd, but the direct executor resolves SHOP_DIR relative to cwd
# (and mounts it into the php container). Absolutize it HERE, while cwd is still the caller's dir, so
# a relative SHOP_DIR=shop doesn't become /tmp/<workspace>/shop once we cd into the scratch dir.
if [ -n "${SHOP_DIR:-}" ]; then
  export SHOP_DIR="$(cd "$SHOP_DIR" 2>/dev/null && pwd || echo "$SHOP_DIR")"
fi
# The playwright executor resolves `node_modules` relative to cwd to find @playwright/test, but each
# scenario runs in a scratch cwd. Remember the caller's node_modules (the workspace where the e2e
# installs Playwright) so run_scenario can symlink it into the scratch dir.
INVOKE_NODE_MODULES=""
[ -d "$PWD/node_modules" ] && INVOKE_NODE_MODULES="$PWD/node_modules"

# LIGHT arm for the containerized executors. execute-bundle refuses a direct/playwright verify without
# REPRO_SANDBOX_ARMED (it fails closed against untrusted agent code). The e2e scenario bundles are
# checked-in and trusted, so we build/pull the same image and set ARMED, but SKIP the iptables egress
# DROP by default — that lockdown contains untrusted code and needs sudo, both moot here. CI passes
# --lockdown for full parity. NOTE: if execute-bundle's gate is ever hardened to *verify* egress is
# actually dropped (rather than trust the sentinel), this local path needs revisiting.
arm_for() {
  # If the caller already armed the sandbox (e.g. the CI job reuses reproduce.md's full arm: images +
  # socat DB relay + /etc/hosts + iptables), respect it and do nothing here.
  [ "${REPRO_SANDBOX_ARMED:-}" = 1 ] && return 0
  case "$1" in
    direct)
      docker build -t repro-php:local - < "$ACTION/dev/php-sandbox.Dockerfile" >/dev/null
      export REPRO_SANDBOX=1 REPRO_SANDBOX_PHP_IMAGE=repro-php:local REPRO_SANDBOX_ARMED=1
      ;;
    playwright)
      local img="${REPRO_SANDBOX_PW_IMAGE:-mcr.microsoft.com/playwright:v1.61.1-noble}"
      docker pull "$img" >/dev/null
      export REPRO_SANDBOX=1 REPRO_SANDBOX_PW_IMAGE="$img" REPRO_SANDBOX_ARMED=1
      ;;
    http) : ;; # host-side, no container needed
  esac
  if [ "$LOCKDOWN" = 1 ] && [ "$1" != http ]; then
    sudo iptables -I DOCKER-USER -j DROP
  fi
}

run_scenario() {
  local sc="$1" sdir ws executor verdict expected
  sdir="$HERE/scenarios/$sc"
  [ -d "$sdir" ] || { echo "FAIL $sc: no such scenario"; return 1; }
  ws=$(mktemp -d)
  ( # subshell so cwd + exported arm env stay isolated per scenario
    set -e
    cd "$ws"
    # Give the playwright executor a node_modules (with @playwright/test) to resolve from this scratch cwd.
    [ -n "$INVOKE_NODE_MODULES" ] && ln -s "$INVOKE_NODE_MODULES" node_modules
    REPRO_BIN="$REPRO" bash "$HERE/agents/replay.sh" "$sdir"
    $REPRO validate
    executor=$(jq -r '.executor' reproduction-plan.json)
    arm_for "$executor"

    REPRO_ALLOW_VERIFY=1 TARGET=reported REPRO_RESOLVED_VERSION="${REPORTED_VERSION:-local}" $REPRO verify || true
    mkdir -p artifacts/repro-reported; [ -f result.json ] && cp result.json artifacts/repro-reported/
    REPRO_ALLOW_VERIFY=1 TARGET=trunk REPRO_RESOLVED_VERSION=trunk $REPRO verify || true
    mkdir -p artifacts/repro-trunk; [ -f result.json ] && cp result.json artifacts/repro-trunk/

    mkdir -p artifacts/repro-plan
    for f in reproduction-plan.json fixtures.json repro.spec.ts ReproTest.php; do
      [ -f "$f" ] && cp "$f" artifacts/repro-plan/
    done

    ART=artifacts $STRIP "$ACTION/report/verdict.ts" > verdict.out
    verdict=$(sed -n 's/^verdict=//p' verdict.out)
    ART=artifacts OUT=comment.md \
      VERDICT="$verdict" \
      UNSURE="$(sed -n 's/^unsure_reason=//p' verdict.out)" \
      FIX="$(sed -n 's/^fix_candidate=//p' verdict.out)" \
      RUN_URL="local-e2e" \
      $STRIP "$ACTION/report/comment.ts"

    expected=$(jq -r '.verdict' "$sdir/expected.json")
    echo "── $sc: verdict=$verdict (expected $expected)  [artifacts: $ws]"
    if [ "$verdict" != "$expected" ]; then
      echo "FAIL $sc: expected '$expected', got '$verdict'"; sed 's/^/    /' verdict.out; exit 1
    fi
    while IFS= read -r needle; do
      [ -z "$needle" ] && continue
      grep -qF "$needle" comment.md || echo "  WARN $sc: comment.md missing '$needle'"
    done < <(jq -r '.commentIncludes[]?' "$sdir/expected.json")
    echo "PASS $sc"
  )
}

rc=0
for sc in "${SCENARIOS[@]}"; do
  run_scenario "$sc" || rc=1
done
if [ "$rc" = 0 ]; then echo "e2e: all scenarios passed"; else echo "e2e: FAILURES"; fi
exit "$rc"
