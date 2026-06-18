#!/usr/bin/env bash
# `direct` executor. Runs a generated PHPUnit integration test that instantiates the
# service/route directly — for bugs that can't fire faithfully through store-api or the
# UI (license-gated, internal services, heavy domain setup; your multi-warehouse class).
#
# expect = HEALTHY: the test asserts the FIXED behaviour, so it FAILS on the buggy
# version (=> reproduced) and PASSES when healthy (=> not_reproduced). A test that
# ERRORS (can't bootstrap/compile — e.g. the reported version lacks an API the test
# uses) => inconclusive (cross-version mismatch), never a bogus reproduced.
#
# Env: ANALYSIS, OUT, TARGET (req), SHOP_DIR (default shop),
#      PHPUNIT_REPORT (advanced/testing: parse this report instead of running phpunit)
set -euo pipefail

ANALYSIS=${ANALYSIS:-analysis.json}
OUT=${OUT:-result.json}
: "${TARGET:?TARGET is required}"
SHOP=${SHOP_DIR:-shop}
VERSION=$(jq -r '.version // "unknown"' "$ANALYSIS")
SPEC=$(jq -r '.script_path // "ReproTest.php"' "$ANALYSIS")
SCRIPT=""; [ -f "$SPEC" ] && SCRIPT=$(cat "$SPEC")

REPORT=$(mktemp); trap 'rm -f "$REPORT"' EXIT
if [ -n "${PHPUNIT_REPORT:-}" ]; then
  cp "$PHPUNIT_REPORT" "$REPORT"
else
  [ -f "$SPEC" ] || { echo "::error::generated test '$SPEC' not found"; exit 1; }
  # Place the test where Shopware's PSR-4 autoload-dev (Shopware\Tests\Integration\) finds it.
  mkdir -p "$SHOP/tests/integration/Repro"
  cp "$SPEC" "$SHOP/tests/integration/Repro/ReproTest.php"
  set +e
  ( cd "$SHOP" && APP_ENV=test php vendor/bin/phpunit --colors=never \
      tests/integration/Repro/ReproTest.php ) >"$REPORT" 2>&1
  set -e
fi
# Keep the FULL output as a leg artifact (the workflow uploads phpunit-output.txt) — a
# trimmed excerpt once cut off the exception MESSAGE and made a failure undiagnosable.
cp "$REPORT" phpunit-output.txt || true
TAIL=$(tail -c 1500 "$REPORT" | tr -d '\r' | tr -s ' ' || true)
# The first PHPUnit failure/error block — the "N) Class::method" line + its message, up to the
# blank line before the stack trace ("N) " prefix stripped). EMPTY for a passing (OK) report.
# Uses awk (not grep) + `|| true` so a no-match NEVER aborts the script under set -e/pipefail:
# a passing leg MUST map to not_reproduced, not crash with exit 1 and no output (a real bug).
BLOCK=$(awk '/^[0-9]+\)[[:space:]]/{f=1} f&&NF==0{exit} f{print}' "$REPORT" 2>/dev/null \
  | sed '1s/^[0-9]*)[[:space:]]*//' | head -c 1200 || true)
ERRHEAD=$(printf '%s' "$BLOCK" | tr '\n' ' ' | tr -s ' ' | head -c 700 || true)

# Map the PHPUnit summary. OK => healthy; FAILURES => symptom; ERRORS/fatal/no-tests =>
# the test couldn't run (likely a cross-version API mismatch) => inconclusive.
# DETAIL = the human-facing failure message, surfaced (comment + run summary) ONLY for a
# reproduced leg — a passing leg has no message; an errored leg's reason lives in blocked_reason.
DETAIL=""
if grep -qE '^OK( |\()' "$REPORT"; then
  STATUS="not_reproduced"; MATCHED="true"; REASON_TEXT=""; REPORTER="PHPUnit OK (healthy)"
elif grep -q 'FAILURES!' "$REPORT"; then
  STATUS="reproduced"; MATCHED="false"; REASON_TEXT=""; DETAIL="$BLOCK"
  REPORTER=$(printf '%s' "$BLOCK" | head -1); [ -n "$REPORTER" ] || REPORTER="assertion failed (symptom present)"
elif grep -qE 'ERRORS!|No tests executed|Fatal error|PHP Fatal|Uncaught' "$REPORT"; then
  # An ERROR is normally a bootstrap/compile problem => inconclusive. BUT when the plan
  # declares the symptom as an exception pattern (assertion.symptom_pattern) and the error
  # text MATCHES it, the throw IS the reproduction — regardless of where in the test it
  # fired (DAL writes run indexers synchronously, so the symptom often escapes the
  # try/catch the test author placed around a later explicit call).
  SYMPTOM=$(jq -r '.assertion.symptom_pattern // empty' "$ANALYSIS")
  if [ -n "$SYMPTOM" ] && grep -qE "$SYMPTOM" "$REPORT"; then
    STATUS="reproduced"; MATCHED="false"; REASON_TEXT=""
    DETAIL="${BLOCK:-symptom exception matched '${SYMPTOM}': $(grep -m1 -E "$SYMPTOM" "$REPORT" | tr -s ' ' | head -c 400 || true)}"
    REPORTER="symptom exception matched '${SYMPTOM}'"
  else
    STATUS="inconclusive"; MATCHED="null"
    REASON_TEXT="PHPUnit could not run the test (errored before/outside the symptom assertion): ${ERRHEAD:-$TAIL} — full output in the leg artifact's phpunit-output.txt"
    REPORTER="PHPUnit errored (test could not run)"
  fi
else
  STATUS="blocked"; MATCHED="null"
  REASON_TEXT="PHPUnit produced no recognisable result: $TAIL"
  REPORTER="PHPUnit produced no result"
fi

jq -n \
  --argjson issue "$(jq -r '.issue' "$ANALYSIS")" \
  --arg target "$TARGET" --arg version "$VERSION" --arg status "$STATUS" \
  --argjson matched "$MATCHED" --arg script "$SCRIPT" --arg reporter "$REPORTER" \
  --arg detail "$DETAIL" --arg reason_text "$REASON_TEXT" '{
    schema_version: "1", issue: $issue, target: $target, version: $version, executor: "direct",
    status: $status,
    assertion: { expect: "test passes (healthy)", actual: $reporter, matched: $matched },
    duration_s: 0,
    evidence: { script: $script, script_lang: "php", reporter_output: $reporter,
      failure_detail: $detail,
      http: [], artifacts: [{ kind: "phpunit-test", name: "ReproTest.php" }], truncated: false },
    blocked_reason: (if $reason_text == "" then null else $reason_text end)
  }' > "$OUT"

# Surface the PHPUnit failure block on THIS leg job's own run summary — only when there is a
# real message (a reproduced leg). A passing or inconclusive leg adds nothing here.
if [ -n "$DETAIL" ] && [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
  { echo "### Reproduce ${TARGET}: \`${STATUS}\` — PHPUnit"; echo; echo '```'; printf '%s\n' "$DETAIL"; echo '```'; } >> "$GITHUB_STEP_SUMMARY"
fi

echo "status=$STATUS  ($REPORTER)"
