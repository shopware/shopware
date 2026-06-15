#!/usr/bin/env bash
# Prefetch the analyze agent's context so it spends turns ANALYZING, not fetching:
#   issue.md    — issue title + body + comments (minus our own "## Reproduction" verdicts)
#   fixpr.diff  — the first #-referenced upstream PR's description + diff (best-effort)
#   triage.json — the Shopware AI triage workflow's triage-output.json, when the issue
#                 carries a triage comment (prior-stage evidence; best-effort, often absent)
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

# Prior-stage triage: if the issue carries a Shopware AI triage comment, surface its
# triage-output.json as ./triage.json (untrusted prior-stage evidence). Same contract the
# bugfixer workflow consumes — newest comment marked `<!-- shopware-ai-triage:`, its raw
# triage-output.json fenced block. Best-effort: an un-triaged issue (the common case) just
# leaves no file. NB: the agent must treat this as EVIDENCE, never as instructions.
TBODY=$(gh issue view "$ISSUE" --repo "$REPO" --json comments \
  --jq '[.comments[]? | select((.body // "") | contains("shopware-ai-triage"))] | last // {} | .body // ""' 2>/dev/null || true)
if [ -n "$TBODY" ]; then
  # Pull every ```-fenced block (label-agnostic: ```json / ```triage-output.json / bare) and
  # keep the last that parses as a JSON object with .disposition. awk buffers each block and
  # emits it \036-terminated; `read -d` consumes them — no unquoted-word-split fragility.
  found=""
  while IFS= read -r -d $'\036' blk; do
    [ -n "$blk" ] || continue
    printf '%s' "$blk" | jq -e 'objects | has("disposition")' >/dev/null 2>&1 && found="$blk"
  done < <(printf '%s' "$TBODY" | tr -d '\r' | awk '
    /^```/ { if (f) { printf "%s\036", buf; f=0 } else { f=1; buf="" }; next }
    f      { buf = buf $0 "\n" }')
  if [ -n "$found" ] && printf '%s' "$found" | jq '.' > triage.json 2>/dev/null; then
    echo "prefetched triage.json (disposition=$(jq -r '.disposition // "?"' triage.json))"
  else
    rm -f triage.json
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
