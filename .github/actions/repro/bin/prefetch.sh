#!/usr/bin/env bash
# Prefetch the analyze agent's context so it spends turns ANALYZING, not fetching:
#   issue.md   — issue title + body + comments (minus our own "## Reproduction" verdicts)
#   fixpr.diff — the first #-referenced upstream PR's description + diff (best-effort)
#
# Env: ISSUE (req), GH_TOKEN (req), REPO (issue repo; default $GITHUB_REPOSITORY),
#      UPSTREAM (PR-lookup repo; default shopware/shopware)
set -euo pipefail

: "${ISSUE:?ISSUE is required}"
REPO=${REPO:-${GITHUB_REPOSITORY:?REPO or GITHUB_REPOSITORY required}}
UPSTREAM=${UPSTREAM:-shopware/shopware}

# Issue title + body + COMMENTS — comments often hold the real repro steps, affected
# version, and the "closed by #PR" cross-reference. EXCLUDE our own prior verdict comments
# (they all start with "## Reproduction") to avoid a feedback loop / wasted context.
gh issue view "$ISSUE" --repo "$REPO" --json title,body,comments \
  --jq '"# " + .title + "\n\n" + (.body // "") + "\n\n## Comments\n\n" + ([.comments[]? | select(((.body // "") | contains("## Reproduction")) | not) | "**@" + (.author.login // "?") + ":** " + (.body // "")] | join("\n\n"))' \
  > issue.md 2>/dev/null || echo "(issue unavailable)" > issue.md
head -c 60000 issue.md > issue.cap && mv issue.cap issue.md # bound context

# First #-referenced number (in body OR comments) that is a real upstream PR →
# its title + body + diff (the diff carries the regression test). Best-effort.
for n in $(grep -oE '#[0-9]{3,}' issue.md | tr -d '#' | sort -un); do
  if gh pr view "$n" --repo "$UPSTREAM" --json title,body >/tmp/pv 2>/dev/null; then
    { echo "# Fix PR $UPSTREAM#$n"; jq -r '.title + "\n\n" + (.body // "")' /tmp/pv;
      echo; echo "## Diff"; gh pr diff "$n" --repo "$UPSTREAM" 2>/dev/null | head -c 40000; } > fixpr.diff
    echo "prefetched fix PR $UPSTREAM#$n"; break
  fi
done
echo "prefetched issue.md ($(wc -c <issue.md) bytes)$([ -f fixpr.diff ] && echo ", fixpr.diff ($(wc -c <fixpr.diff) bytes)")"
