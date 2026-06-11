#!/usr/bin/env bash
# Dispatch a single reproduce leg to its executor and write result.json. The plan's
# `executor` (http | playwright | direct) picks the cheapest faithful runner; an
# unknown value degrades to an `inconclusive` result rather than failing the leg.
#
# Env: TARGET (req, leg role/name), EXECUTOR (optional override; else derived from the
#      plan's .executor), APP_URL + SW_ACCESS_KEY (live shop coords for http/playwright;
#      unused by direct), ANALYSIS (default analysis.json), OUT (default result.json),
#      SHOP_DIR (direct, default shop).
set -euo pipefail

: "${TARGET:?TARGET is required}"
ANALYSIS=${ANALYSIS:-analysis.json}
OUT=${OUT:-result.json}
# The executor is a property of the plan; callers may override via env, else read it
# from the analysis so both workflows can invoke this script identically.
EXECUTOR=${EXECUTOR:-$(jq -r '.executor // ""' "$ANALYSIS")}
: "${EXECUTOR:?EXECUTOR not set and no .executor in $ANALYSIS}"

case "$EXECUTOR" in
  http)
    ANALYSIS="$ANALYSIS" OUT="$OUT" bash .github/actions/repro/bin/run-http.sh
    ;;
  playwright)
    ANALYSIS="$ANALYSIS" OUT="$OUT" bash .github/actions/repro/bin/run-playwright.sh
    ;;
  direct)
    ANALYSIS="$ANALYSIS" OUT="$OUT" SHOP_DIR="${SHOP_DIR:-shop}" bash .github/actions/repro/bin/run-direct.sh
    ;;
  *)
    echo "::warning::unknown executor '$EXECUTOR'; emitting inconclusive"
    jq -n --argjson issue "$(jq -r .issue "$ANALYSIS")" --arg target "$TARGET" \
      --arg version "$(jq -r .version "$ANALYSIS")" --arg executor "$EXECUTOR" '{
        schema_version:"1", issue:$issue, target:$target, version:$version, executor:$executor,
        status:"inconclusive", assertion:{expect:null,actual:null,matched:null}, duration_s:0,
        evidence:{script:"", script_lang:"sh", reporter_output:("unknown executor "+$executor),
          http:[], artifacts:[], truncated:false},
        blocked_reason:("unknown executor "+$executor) }' > "$OUT"
    ;;
esac
cat "$OUT"
