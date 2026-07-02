#!/usr/bin/env bash
# MAINTAINER TOOL (not used by the workflow at runtime). Recompile the gh-aw source into its
# committed .lock.yml, then re-apply the two things gh-aw source frontmatter can't express:
#
#   1. Allow the sandbox host port 18080 (the Shopware proxy) — only if the lock uses a host-port
#      allowlist (it won't when the agent runs unsandboxed; then this is a no-op).
#   2. Make the trunk/report job run whenever the agent job ran, instead of gating it on the agent
#      emitting a safe-output handoff — the job reads the uploaded artifacts and decides for itself.
#
# Usage: bash .github/actions/reproduce/dev/compile.sh   (run from the repo root; needs `gh aw`)
set -euo pipefail

SRC=.github/workflows/reproduce.md
LOCK=.github/workflows/reproduce.lock.yml
PORT=18080

command -v gh >/dev/null || { echo "gh CLI is required"; exit 1; }
gh aw compile "$SRC"
[ -f "$LOCK" ] || { echo "::error::compile did not produce $LOCK"; exit 1; }

# 1. host-port allowlist (sandbox only)
if grep -q -- '--allow-host-ports 80,443,8080' "$LOCK"; then
  perl -0pi -e "s/--allow-host-ports 80,443,8080(?!,${PORT})/--allow-host-ports 80,443,8080,${PORT}/g" "$LOCK"
  echo "patched: host port ${PORT} allowed"
else
  echo "skipped host-port patch (no sandbox host-port allowlist in lock)"
fi

# 2. run the trunk job from artifacts, not from a safe-output handoff
gate=" && contains(needs.agent.outputs.output_types, 'reproduce_on_trunk')"
if grep -qF "$gate" "$LOCK"; then
  perl -0pi -e "s/\Q$gate\E//g" "$LOCK"
  echo "patched: trunk job runs whenever the agent job ran"
else
  echo "note: safe-output gate not found (already patched, or job name differs) — verify $LOCK by hand"
fi

echo "done — review and commit $LOCK"
