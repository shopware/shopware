#!/usr/bin/env bash
# MAINTAINER TOOL (not used by the workflows at runtime). Recompiles the reproduce gh-aw sources into
# their committed .lock.yml files, then re-applies the handful of things gh-aw's source frontmatter
# cannot express. Everything below is a post-compile patch on the GENERATED lock, because the lock is
# regenerated from scratch on every `gh aw compile` — so these edits must live here, not by hand.
#
# ── What each patch adds to the awf (Agentic Workflow Firewall) lock ──────────────────────────────
#
#   [P1] Reach the live Shopware shop from inside the sandbox.
#        When the agent is sandboxed, gh-aw runs it behind awf with a host-port allowlist:
#            --allow-host-ports 80,443,8080
#        awf SILENTLY DROPS any host port not on that list, so the agent cannot connect to the shop
#        (Symfony serves it on host port ${SHOP_PORT}; the agent reaches the runner host as
#        `host.docker.internal`). The default list is unusable for us: 8080 is gh-aw's own MCP
#        Gateway and 80/443 need privilege/TLS. gh-aw v0.81.2 exposes NO frontmatter to extend this
#        general list (its `service-ports` maps to `--allow-host-service-ports`, which is for Docker
#        service containers, not a host process), so we append ${SHOP_PORT} here. No-op on an
#        unsandboxed lock (no allowlist present).
#
#   [P2] Run the deterministic report jobs whenever the agent job ran (reproduce.md only).
#        gh-aw gates safe-output jobs on the agent emitting a matching safe-output handoff. We want
#        BOTH post-agent jobs (reproduce_on_trunk + reproduce_report) to run unconditionally after
#        the agent job, because they read the uploaded artifacts and decide the outcome themselves
#        (the agent never decides). We strip the
#        `&& contains(needs.agent.outputs.output_types, '<job>')` gate from each.
#
#   [P3] Chain reproduce_report AFTER reproduce_on_trunk (reproduce.md only).
#        The two jobs are split by trust: reproduce_on_trunk re-runs the UNTRUSTED agent-authored
#        bundle on trunk with a read-only token and uploads the trunk leg as a data artifact;
#        reproduce_report holds the write token and turns that artifact into the verdict + comment.
#        gh-aw compiles every safe-output job with `needs: [agent, detection]` and no ordering
#        between them, so we add reproduce_on_trunk to reproduce_report's `needs` — otherwise report
#        races the trunk re-run and downloads a not-yet-uploaded leg.
#
#   [P4] Gate the two bundle-consuming jobs on threat detection (reproduce.md only).
#        gh-aw gives the built-in `safe_outputs` job a `&& needs.detection.result == 'success'` gate
#        but leaves our custom reproduce_on_trunk (executes the untrusted spec) and reproduce_report
#        (posts the public comment) ungated, so threat-detection would be advisory-only. Both already
#        `needs: [..., detection]`, so we append the same gate to each job's `if:` — a flagged bundle
#        now SKIPS the trunk re-run and the comment instead of executing/publishing. Idempotent via
#        the negative lookahead; the already-gated safe_outputs line is skipped.
#
# Usage: bash .github/actions/reproduce/dev/compile.sh [source.md ...]
#   No args → compile+patch every source in SOURCES below.
#   With args → compile+patch only the given source(s). Run from the repo root; needs `gh aw`.
set -euo pipefail

# Host port Symfony serves the shop on (see steps/finish-provision.sh: SYMFONY_PORT). Keeping the
# shop on its existing port and allowlisting it is simpler than moving it, and avoids touching the
# shared provision script (which the unsandboxed reproduce.md legs also use).
SHOP_PORT=8000

# The reproduce gh-aw source whose lock we own. Override via CLI args (see Usage).
if [ "$#" -gt 0 ]; then
  SOURCES=("$@")
else
  SOURCES=(
    .github/workflows/reproduce.md
  )
fi

command -v gh >/dev/null || { echo "gh CLI is required"; exit 1; }

patch_lock() {
  local lock=$1

  # [P1] Append the shop's host port to awf's allowlist. The negative lookahead keeps it idempotent
  # if the port is somehow already present. Guarded so it is a clean no-op on an unsandboxed lock.
  if grep -q -- '--allow-host-ports 80,443,8080' "$lock"; then
    perl -0pi -e "s/--allow-host-ports 80,443,8080(?!,${SHOP_PORT})/--allow-host-ports 80,443,8080,${SHOP_PORT}/g" "$lock"
    echo "  [P1] host port ${SHOP_PORT} added to awf --allow-host-ports (shop reachable)"
  else
    echo "  [P1] skipped — no sandbox host-port allowlist in this lock (agent runs unsandboxed)"
  fi

  # [P2] Drop the safe-output gate from both post-agent jobs so they run whenever the agent job ran.
  if grep -qE "&& contains\(needs\.agent\.outputs\.output_types, '(reproduce_on_trunk|reproduce_report)'\)" "$lock"; then
    perl -0pi -e "s/ && contains\(needs\.agent\.outputs\.output_types, '(?:reproduce_on_trunk|reproduce_report)'\)//g" "$lock"
    echo "  [P2] safe-output gates removed (reproduce_on_trunk + reproduce_report run whenever the agent job ran)"
  else
    echo "  [P2] skipped — no reproduce_* safe-output gate in this lock"
  fi

  # [P3] Make reproduce_report depend on reproduce_on_trunk (anchored to the report job's needs block
  # so the identical agent/detection lines in other jobs are untouched). Idempotent via lookahead.
  if grep -qE '^  reproduce_report:' "$lock"; then
    perl -0pi -e "s/(  reproduce_report:\n    needs:\n      - agent\n      - detection\n)(?!      - reproduce_on_trunk\n)/\${1}      - reproduce_on_trunk\n/" "$lock"
    echo "  [P3] reproduce_report now needs reproduce_on_trunk (report waits for the trunk leg)"
  else
    echo "  [P3] skipped — no reproduce_report job in this lock"
  fi

  # [P4] Append the threat-detection gate to the two bundle-consuming jobs' `if:` (see header). The
  # negative lookahead makes it idempotent and skips the already-gated built-in safe_outputs line.
  if grep -qE "^    if: \(!cancelled\(\)\) && needs\.agent\.result != 'skipped'\$" "$lock"; then
    perl -0pi -e "s/(    if: \(!cancelled\(\)\) && needs\.agent\.result != 'skipped')(?! && needs\.detection)/\${1} && needs.detection.result == 'success'/g" "$lock"
    echo "  [P4] threat-detection gate added (reproduce_on_trunk + reproduce_report skip on a flagged bundle)"
  else
    echo "  [P4] skipped — no ungated post-agent job if: in this lock"
  fi
}

for src in "${SOURCES[@]}"; do
  [ -f "$src" ] || { echo "note: $src not found — skipping"; continue; }
  lock=${src%.md}.lock.yml
  echo "== $src =="
  gh aw compile "$src"
  [ -f "$lock" ] || { echo "::error::compile did not produce $lock"; exit 1; }
  patch_lock "$lock"
done

echo "done — review and commit the patched .lock.yml file(s)"
