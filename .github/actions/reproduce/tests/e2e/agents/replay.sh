#!/usr/bin/env bash
# Deterministic stand-in for the LLM agent (no model, no cost, no randomness).
#
# It does what the real agent does, minus the thinking: exercises the read-only inspection CLI against
# the live shop, "authors" the bundle by copying the scenario's canned files into the workspace cwd,
# then runs its own `try` preview. The deterministic legs (driven by drive.sh) decide the verdict.
#
# Arg: $1 = scenario directory. Env: REPRO_BIN = the repro CLI invocation (e.g. "node --… repro.ts").
set -euo pipefail
sdir="$1"
repro=${REPRO_BIN:?REPRO_BIN must be set to the repro CLI invocation}

# 1) Look around the live shop. Best-effort: this is the agent "getting oriented" (real coverage of
#    the schema/search inspection verbs), not an assertion — a limited/mock shop may not answer them.
$repro schema product   >/dev/null 2>&1 || echo "  (agent) repro schema unavailable on this shop"
$repro search country '{"limit":1}' >/dev/null 2>&1 || echo "  (agent) repro search unavailable on this shop"

# 2) The "edits": author the bundle files into the workspace.
cp "$sdir/reproduction-plan.json" .
for f in fixtures.json ReproTest.php repro.spec.ts; do
  [ -f "$sdir/$f" ] && cp "$sdir/$f" .
done

# 3) The agent's own preview loop, run for real against the live shop. Best-effort feedback only — the
#    official result comes from `verify`; here the agent would iterate until try looks right.
$repro try >/dev/null 2>&1 || echo "  (agent) repro try preview did not pass (the agent would iterate here)"

echo "  (agent) authored bundle for $(basename "$sdir")"
