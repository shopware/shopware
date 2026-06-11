#!/usr/bin/env bash
# Write a `blocked` result.json for a leg whose env never became usable (provision or
# seed failed). Blocked = infra signal, not a bug verdict — the report suppresses the
# public comment for it.
#
# Env: TARGET (req, leg role/name), FAILED (provision|seed; default provision),
#      ANALYSIS (default analysis.json), OUT (default result.json).
set -euo pipefail

: "${TARGET:?TARGET is required}"
FAILED=${FAILED:-provision}
ANALYSIS=${ANALYSIS:-analysis.json}
OUT=${OUT:-result.json}

jq -n --argjson issue "$(jq -r .issue "$ANALYSIS")" --arg target "$TARGET" \
  --arg version "$(jq -r .version "$ANALYSIS")" --arg executor "$(jq -r .executor "$ANALYSIS")" --arg failed "$FAILED" '{
    schema_version:"1", issue:$issue, target:$target, version:$version, executor:$executor,
    status:"blocked", assertion:{expect:null,actual:null,matched:null}, duration_s:0,
    evidence:{script:"", script_lang:"sh", reporter_output:($failed+" failed for this leg"),
      http:[], artifacts:[], truncated:false},
    blocked_reason:($failed+" step failed (dead env)") }' > "$OUT"
cat "$OUT"
