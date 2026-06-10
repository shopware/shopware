#!/usr/bin/env bash
# Real `playwright` executor. Runs the spec Analyze generated ONCE (repro.spec.ts,
# reused by both legs) against the leg's APP_URL, then maps the result to a verdict.
#
# expect = healthy: the spec asserts the FIXED behaviour, so it FAILS on the buggy
# version (=> reproduced) and PASSES when healthy (=> not_reproduced).
#
# Evidence: the verbatim spec + the Playwright JSON summary; screenshots/video/trace
# are written under test-results/ and uploaded by the workflow.
#
# Env:
#   ANALYSIS     analysis.json                         (default: analysis.json)
#   OUT          result.json                           (default: result.json)
#   APP_URL      base URL of the running shop          (required)
#   TARGET       reported | trunk                      (required)
#   PW_REPORT    parse this existing JSON report instead of running (testing hook)
set -euo pipefail

ANALYSIS=${ANALYSIS:-analysis.json}
OUT=${OUT:-result.json}
: "${APP_URL:?APP_URL is required}"
: "${TARGET:?TARGET is required}"
VERSION=$(jq -r '.version // "unknown"' "$ANALYSIS")
SPEC=$(jq -r '.script_path // "repro.spec.ts"' "$ANALYSIS")
REPORT=${PW_REPORT:-pw-report.json}

if [ -z "${PW_REPORT:-}" ]; then
  [ -f "$SPEC" ] || { echo "::error::generated spec '$SPEC' not found"; exit 1; }
  # one-shot run; the config's baseURL reads $APP_URL.
  set +e
  APP_URL="$APP_URL" npx playwright test "$SPEC" \
    --config .github/actions/repro/repro.playwright.config.ts --reporter=json >"$REPORT" 2>pw-stderr.txt
  set -e
fi

SCRIPT=""; [ -f "$SPEC" ] && SCRIPT=$(cat "$SPEC")

# Map via the JSON reporter's stats: unexpected = failing tests.
if ! jq -e . "$REPORT" >/dev/null 2>&1; then
  STATUS="blocked"; MATCHED="null"; ACTUAL="null"; REASON="\"playwright produced no parseable report (env not READY?)\""
  REPORTER="runner error: $(tail -1 pw-stderr.txt 2>/dev/null || echo unknown)"
else
  EXPECTED=$(jq -r '.stats.expected // 0' "$REPORT")
  UNEXPECTED=$(jq -r '.stats.unexpected // 0' "$REPORT")
  REASON="null"
  if [ "$EXPECTED" = 0 ] && [ "$UNEXPECTED" = 0 ]; then
    STATUS="inconclusive"; MATCHED="null"; ACTUAL="\"no tests ran\""
    REPORTER="no tests executed"
  elif [ "$UNEXPECTED" -gt 0 ]; then
    STATUS="reproduced"; MATCHED="false"; ACTUAL="\"$UNEXPECTED failing\""
    REPORTER=$(jq -r '[.. | objects | select(.status?=="failed") | .error?.message // empty] | first // "assertion failed"' "$REPORT" | head -c 300)
  else
    STATUS="not_reproduced"; MATCHED="true"; ACTUAL="\"$EXPECTED passing\""
    REPORTER="all $EXPECTED test(s) passed (healthy)"
  fi
fi

jq -n \
  --argjson issue "$(jq -r '.issue' "$ANALYSIS")" \
  --arg target "$TARGET" --arg version "$VERSION" --arg status "$STATUS" \
  --argjson matched "$MATCHED" --argjson actual "$ACTUAL" \
  --arg script "$SCRIPT" --arg reporter "$REPORTER" --argjson reason "$REASON" '{
    schema_version: "1",
    issue: $issue,
    target: $target,
    version: $version,
    executor: "playwright",
    status: $status,
    assertion: { expect: "spec passes (healthy)", actual: $actual, matched: $matched },
    duration_s: 0,
    evidence: {
      script: $script,
      script_lang: "ts",
      reporter_output: $reporter,
      http: [],
      artifacts: [{ kind: "playwright-results", name: "test-results/", run_artifact: ("repro-" + $target) }],
      truncated: false
    },
    blocked_reason: $reason
  }' > "$OUT"

echo "status=$STATUS  ($REPORTER)"
