#!/usr/bin/env bash
# Compute the leg matrix from the analyze plan, with the three graceful pre-provision
# exits (missing plan / needs_info / < 0.4 low confidence) — each posts a comment (when
# POST=true) and emits an EMPTY matrix so the reproduce/report jobs skip cleanly.
#
# Env: SKIP_REPORTED, BRANCH (default trunk), ISSUE (req), POST, GH_TOKEN,
#      REPO (default $GITHUB_REPOSITORY), ANALYSIS (default analysis.json).
# Emits `targets=<json array>` to $GITHUB_OUTPUT (and stdout).
set -euo pipefail

: "${ISSUE:?ISSUE is required}"
BRANCH=${BRANCH:-trunk}
SKIP_REPORTED=${SKIP_REPORTED:-false}
POST=${POST:-false}
REPO=${REPO:-${GITHUB_REPOSITORY:-}}
ANALYSIS=${ANALYSIS:-analysis.json}

out () { [ -n "${GITHUB_OUTPUT:-}" ] && echo "$1=$2" >> "$GITHUB_OUTPUT"; echo "$1=$2"; }
comment () { [ "$POST" = "true" ] && gh issue comment "$ISSUE" --repo "$REPO" --body "$1" || true; }

# No plan file: the agent errored or exceeded its turn budget without writing one.
# Degrade gracefully (visible comment + clean skip) instead of an opaque red run.
if [ ! -f "$ANALYSIS" ]; then
  echo "::warning::analyze produced no $ANALYSIS (agent error or exceeded turn budget)"
  comment "## Reproduction — could not analyze"$'\n\n'"The analyzer did not produce a repro plan within its turn budget (likely an over-exploring UI/playwright analysis). No reproduction was run. A human can review the issue, or re-trigger to retry."
  out targets "[]"
  exit 0
fi
cat "$ANALYSIS"

# Analyze judged the issue too ambiguous to reproduce faithfully → ask, don't guess.
NEEDS=$(jq -r '.needs_info // empty' "$ANALYSIS")
if [ -n "$NEEDS" ]; then
  echo "::warning::analyze needs info: $NEEDS"
  comment "## Reproduction — needs info"$'\n\n'"$NEEDS"
  out targets "[]"
  exit 0
fi

# Pre-provision bail-out: a plan the agent itself doesn't trust (< 0.4) is almost always a
# guess. Provisioning two installs to test a guess wastes the expensive part of the run, so
# ask a human to confirm the draft FIRST. (0.4–0.7 still runs → needs_human_review.)
CONF=$(jq -r '.confidence // 1' "$ANALYSIS")
if awk "BEGIN{exit !($CONF < 0.4)}"; then
  REASON=$(jq -r '.confidence_reason // .blocked_reason // "no reason given"' "$ANALYSIS")
  SCEN=$(jq -r '(.scenario // []) | map("- " + .) | join("\n")' "$ANALYSIS")
  echo "::warning::analyze low confidence ($CONF) — bailing before provision: $REASON"
  comment "## Reproduction — low confidence, not run"$'\n\n'"The analyzer produced a draft plan it is not confident is faithful (confidence \`$CONF\`), so the reproduction was **not** run (to avoid provisioning two installs to test a guess)."$'\n\n'"**Why:** $REASON"$'\n\n'"**Draft scenario it would have tried:**"$'\n'"$SCEN"$'\n\n'"_Confirm or correct the steps above and re-trigger to run it._"
  out targets "[]"
  exit 0
fi

VERSION=$(jq -r '.version' "$ANALYSIS")
# Each leg carries the ref to display/provision. The trunk leg uses $BRANCH (default trunk)
# so you can reproduce against a fix branch. Trunk-only when explicitly requested
# (skip_reported) or when reported == the branch.
if [ "$SKIP_REPORTED" = "true" ] || [ "$VERSION" = "$BRANCH" ]; then
  TARGETS=$(jq -nc --arg b "$BRANCH" '[$b]')
else
  TARGETS=$(jq -nc --arg v "$VERSION" --arg b "$BRANCH" '[$v,$b]')
fi
out targets "$TARGETS"
