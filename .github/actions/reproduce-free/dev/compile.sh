#!/usr/bin/env bash
# MAINTAINER TOOL (not used by the workflow at runtime). Recompiles the free-variant gh-aw source
# into its committed .lock.yml, then re-applies the two things gh-aw source frontmatter cannot
# express. Everything below is a post-compile patch on the GENERATED lock, because the lock is
# regenerated from scratch on every `gh aw compile` — so these edits must live here, not by hand.
#
# ── What each patch adds to the compiled lock ─────────────────────────────────────────────────────
#
#   [P2] Run the deterministic report jobs whenever the agent job ran.
#        gh-aw gates safe-output jobs on the agent emitting a matching safe-output handoff. We want
#        BOTH post-agent jobs (reproduce_free_on_trunk + reproduce_free_report) to run
#        unconditionally after the agent job, because they read the uploaded artifacts and decide
#        the outcome themselves (the agent never decides). We strip the
#        `&& contains(needs.agent.outputs.output_types, '<job>')` gate from each.
#
#   [P3] Chain reproduce_free_report AFTER reproduce_free_on_trunk.
#        The two jobs are split by trust: reproduce_free_on_trunk re-runs the UNTRUSTED
#        agent-authored bundle on trunk with a read-only token and uploads the trunk leg as a data
#        artifact; reproduce_free_report holds the write token and turns that artifact into the
#        verdict + comment. gh-aw compiles safe-output jobs with no ordering between them, so we add
#        reproduce_free_on_trunk to reproduce_free_report's `needs` — otherwise report races the
#        trunk re-run and downloads a not-yet-uploaded leg. This variant disables threat detection
#        (it requires the sandbox, which we turn off), so gh-aw emits the flow-style `needs: agent`
#        shape; we rewrite that to a block list including the trunk job, then VERIFY the wiring so a
#        future gh-aw shape change fails the compile loudly instead of silently racing.
#
#   NOTE: unlike the strict variant, there is no host-port allowlist patch — this workflow runs the
#   agent UNSANDBOXED on purpose (see the frontmatter), so there is no awf firewall to extend.
#
# Usage: bash .github/actions/reproduce-free/dev/compile.sh   (run from the repo root; needs `gh aw`)
set -euo pipefail

SRC=.github/workflows/reproduce-free.md
LOCK=.github/workflows/reproduce-free.lock.yml

command -v gh >/dev/null || { echo "gh CLI is required"; exit 1; }
[ -f "$SRC" ] || { echo "::error::$SRC not found — run from the repo root"; exit 1; }

echo "== $SRC =="
gh aw compile "$SRC"
[ -f "$LOCK" ] || { echo "::error::compile did not produce $LOCK"; exit 1; }

# [P2] Drop the safe-output gate from both post-agent jobs so they run whenever the agent job ran.
if grep -qE "&& contains\(needs\.agent\.outputs\.output_types, 'reproduce_free_(on_trunk|report)'\)" "$LOCK"; then
  perl -0pi -e "s/ && contains\(needs\.agent\.outputs\.output_types, '(?:reproduce_free_(?:on_trunk|report))'\)//g" "$LOCK"
  echo "  [P2] safe-output gates removed (the on-trunk + report jobs run whenever the agent job ran)"
else
  echo "  [P2] skipped — no reproduce_free_* safe-output gate in this lock"
fi

# [P3] Make reproduce_free_report depend on reproduce_free_on_trunk. Handle both needs shapes gh-aw
# emits — the block list (threat detection on) and the flow scalar `needs: agent` (detection off) —
# then verify, failing loudly on a shape drift instead of silently racing the report job.
if grep -qE '^  reproduce_free_report:' "$LOCK"; then
  perl -0pi -e "
    s/(  reproduce_free_report:\n    needs:\n      - agent\n      - detection\n)(?!      - reproduce_free_on_trunk\n)/\${1}      - reproduce_free_on_trunk\n/;
    s/(  reproduce_free_report:\n    needs:) agent\n/\${1}\n      - agent\n      - reproduce_free_on_trunk\n/;
  " "$LOCK"
  if awk '/^  reproduce_free_report:/{f=1} f && /- reproduce_free_on_trunk/{ok=1} f && /^  [a-z]/ && !/^  reproduce_free_report:/{exit} END{exit !ok}' "$LOCK"; then
    echo "  [P3] reproduce_free_report now needs reproduce_free_on_trunk (report waits for the trunk leg)"
  else
    echo "::error::[P3] could not wire reproduce_free_report to need reproduce_free_on_trunk — needs shape changed?"
    exit 1
  fi
else
  echo "  [P3] skipped — no reproduce_free_report job in this lock"
fi

echo "done — review and commit $LOCK"
