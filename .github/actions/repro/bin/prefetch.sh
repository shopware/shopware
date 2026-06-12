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
# A previously PROVEN plan for this issue (persisted by persist-plan.sh on a reproducing
# verdict) → ./prior-plan/, so analyze REPLAYS it instead of re-deriving from scratch.
# Re-derivation is a gamble that has regressed a live_bug to inconclusive.
if [ -n "${EVIDENCE_BRANCH:-}" ]; then
  if files=$(gh api "repos/$REPO/contents/plans/issue-$ISSUE?ref=$EVIDENCE_BRANCH" \
      --jq '.[] | .name + " " + .download_url' 2>/dev/null) && [ -n "$files" ]; then
    mkdir -p prior-plan
    while read -r name url; do
      curl -fsSL --proto '=https' --max-time 20 --max-filesize 1048576 -o "prior-plan/$name" "$url" 2>/dev/null || true
    done <<< "$files"
    echo "prefetched PROVEN prior plan ($(ls prior-plan | tr '\n' ' '))"
  fi
fi

# Screenshot attachments → issue-assets/ so the (multimodal) agent can Read them for UI
# bugs. SECURITY: assets are untrusted user content — fetch UNAUTHENTICATED (never attach
# a token to asset hosts), only from GitHub's own attachment hosts, capped in count+size,
# and keep a file only when its MAGIC BYTES say it is an image (videos/HTML/zip are
# dropped; the model cannot watch videos anyway).
mkdir -p issue-assets
i=0
# NB: `|| true` — an issue with no image URLs makes grep exit 1, which pipefail would
# otherwise turn into a prefetch crash (a real miss: every no-screenshot issue died here).
{ grep -oE 'https://(github\.com/user-attachments/assets/[A-Za-z0-9-]+|user-images\.githubusercontent\.com/[A-Za-z0-9./_-]+)' issue.md || true; } \
  | sort -u | head -3 | while read -r url; do
  i=$((i+1)); tmp="issue-assets/.dl-$i"
  curl -fsSL --proto '=https' --max-time 20 --max-filesize 3145728 -o "$tmp" "$url" 2>/dev/null || { rm -f "$tmp"; continue; }
  mime=$(file -b --mime-type "$tmp" 2>/dev/null || echo unknown)
  case "$mime" in
    image/png)  mv "$tmp" "issue-assets/img-$i.png" ;;
    image/jpeg) mv "$tmp" "issue-assets/img-$i.jpg" ;;
    image/gif)  mv "$tmp" "issue-assets/img-$i.gif" ;;
    image/webp) mv "$tmp" "issue-assets/img-$i.webp" ;;
    *) rm -f "$tmp" ;; # not an image (video/other) — drop
  esac
done
rmdir issue-assets 2>/dev/null || true # remove if nothing image-like was kept
[ -d issue-assets ] && echo "prefetched $(ls issue-assets | wc -l | tr -d ' ') screenshot(s) to issue-assets/"
echo "prefetched issue.md ($(wc -c <issue.md) bytes)$([ -f fixpr.diff ] && echo ", fixpr.diff ($(wc -c <fixpr.diff) bytes)")"
