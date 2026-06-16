#!/usr/bin/env bash
# Staged precheck (cheap http run BEFORE the expensive, render-dependent playwright leg).
#
# When analyze attaches a `.precheck` (an http sub-plan) to a *-ui plan, run it first. If it
# is TRUSTED (analyze set `.precheck.trusted=true` — see ANALYZE.md: only when it is derived
# from the fix PR's regression test OR asserts the documented symptom against a real
# store-api/service response) AND returns a CONCLUSIVE status (`reproduced`|`not_reproduced`),
# it stands as the leg verdict and the playwright run is skipped. Otherwise the precheck is
# kept only as corroboration (precheck-result.json) and the caller runs playwright.
#
# A guessed/untrusted precheck NEVER decides the leg, and an inconclusive precheck never does
# either — both preserve the pipeline's core invariant ("never a false `reproduced`"). The
# shared http executor's own guards (401/403 / unparseable → inconclusive) apply here too.
#
# Env: TARGET (req, leg role), APP_URL (req), SW_ACCESS_KEY, SC_ID, ANALYSIS (default
#      analysis.json). Emits `decisive` (true|false) + `precheck_status` to $GITHUB_OUTPUT.
# Writes precheck-result.json when a precheck ran; writes result.json only when decisive.
set -euo pipefail

ANALYSIS=${ANALYSIS:-analysis.json}
: "${TARGET:?TARGET is required}"

out () { [ -n "${GITHUB_OUTPUT:-}" ] && echo "$1=$2" >> "$GITHUB_OUTPUT"; echo "$1=$2"; }

# No precheck attached (the normal case) → nothing to do; the caller runs the primary executor.
HAS=$(jq -r 'if (.precheck.request // .precheck.requests // null) != null then "yes" else "no" end' "$ANALYSIS")
if [ "$HAS" != "yes" ]; then
  out decisive false
  exit 0
fi

TRUSTED=$(jq -r '.precheck.trusted // false' "$ANALYSIS")

# Build a self-contained http plan from .precheck, inheriting issue/version from the parent.
jq '.precheck + {executor:"http", issue:.issue, version:.version}' "$ANALYSIS" > precheck.json

# Run through the shared http executor. Tolerate any failure → inconclusive (never decisive).
set +e
EXECUTOR=http TARGET="$TARGET" ANALYSIS=precheck.json OUT=precheck-result.json \
  bash .github/actions/repro/bin/run-leg.sh
set -e

ST=$(jq -r '.status // "inconclusive"' precheck-result.json 2>/dev/null || echo inconclusive)
out precheck_status "$ST"

# Decisive ONLY when trusted AND conclusive. Untrusted/inconclusive → corroboration only.
if [ "$TRUSTED" = "true" ] && { [ "$ST" = "reproduced" ] || [ "$ST" = "not_reproduced" ]; }; then
  jq '.evidence.reporter_output = ("[decided by trusted store-api precheck] " + (.evidence.reporter_output // ""))
      | .executor = "http (precheck)"' precheck-result.json > result.json
  out decisive true
else
  out decisive false
fi
