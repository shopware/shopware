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

CONFIG=.github/actions/repro/repro.playwright.config.ts
if [ -z "${PW_REPORT:-}" ]; then
  [ -f "$SPEC" ] || { echo "::error::generated spec '$SPEC' not found"; exit 1; }
  # Playwright's testDir is the config's directory, so the spec must live THERE to be
  # collected — a spec at the workspace root is silently ignored (0 tests => "no tests ran").
  # Place it next to the config (mirrors run-direct.sh dropping ReproTest.php under the shop).
  cp "$SPEC" "$(dirname "$CONFIG")/repro.spec.ts"
  set +e
  APP_URL="$APP_URL" npx playwright test \
    --config "$CONFIG" --reporter=json >"$REPORT" 2>pw-stderr.txt
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
  SKIPPED=$(jq -r '.stats.skipped // 0' "$REPORT")
  REASON="null"
  # Every failed/timed-out test's error message — used to classify WHY it failed.
  # Strip ANSI colour codes Playwright embeds, else they leak into the rendered comment.
  ERRS=$(jq -r '[.. | objects | select(.status?=="failed" or .status?=="timedOut") | .error?.message // empty] | join(" || ")' "$REPORT" | perl -pe 's/\e\[[0-9;]*m//g')
  MSG=$(printf '%s' "$ERRS" | tr -s ' \n' '  ' | head -c 300)
  if [ "$EXPECTED" = 0 ] && [ "$UNEXPECTED" = 0 ] && [ "$SKIPPED" = 0 ]; then
    STATUS="inconclusive"; MATCHED="null"; ACTUAL="\"no tests ran\""
    REPORTER="no tests executed"; REASON="\"playwright ran no tests\""
  elif [ "$UNEXPECTED" -gt 0 ]; then
    # A failure is the SYMPTOM (=> reproduced) ONLY when it's a real value assertion on an
    # element that was found. The SAME spec runs on both versions, so a failure because the
    # page/control the repro depends on is ABSENT on this version (cross-version UI drift)
    # must NOT masquerade as the symptom => inconclusive. Precedence: explicit precondition
    # marker, then navigation/connection failure, then a genuine expect() assertion, else
    # an unrecognised locator/timeout failure (a drive failure, not a reproduction).
    if printf '%s' "$ERRS" | grep -q 'PRECONDITION_NOT_FOUND'; then
      STATUS="inconclusive"; MATCHED="null"; ACTUAL="\"precondition missing on $VERSION\""
      REPORTER="precondition absent on this version (UI differs) — $MSG"
      REASON="\"a precondition element the spec depends on is absent on $VERSION (likely cross-version UI drift); the symptom could not be exercised, so this is not a reproduction\""
    elif printf '%s' "$ERRS" | grep -qiE 'net::ERR|ERR_CONNECTION|page\.goto|waiting for navigation|Navigation to .* failed'; then
      STATUS="inconclusive"; MATCHED="null"; ACTUAL="\"could not load the page on $VERSION\""
      REPORTER="navigation/connection failure — $MSG"
      REASON="\"the spec could not load the target page on $VERSION; the symptom cannot be judged\""
    elif printf '%s' "$ERRS" | grep -qE 'expect|Expected:|toBe|toHave|toContain|toEqual'; then
      STATUS="reproduced"; MATCHED="false"; ACTUAL="\"$UNEXPECTED failing\""
      REPORTER=$([ -n "$MSG" ] && printf '%s' "$MSG" || echo "assertion failed")
    else
      STATUS="inconclusive"; MATCHED="null"; ACTUAL="\"$UNEXPECTED failing (non-assertion)\""
      REPORTER="failure was not a value assertion (likely a missing/changed element) — $MSG"
      REASON="\"the failure was a locator/timeout error, not an assertion on a found element; cannot confirm the symptom on $VERSION\""
    fi
  elif [ "$SKIPPED" -gt 0 ] && [ "$EXPECTED" = 0 ]; then
    STATUS="inconclusive"; MATCHED="null"; ACTUAL="\"$SKIPPED skipped\""
    REPORTER="spec skipped (precondition not met on this version)"
    REASON="\"the spec skipped itself (test.skip): the repro's precondition is not met on $VERSION\""
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
