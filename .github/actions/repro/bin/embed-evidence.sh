#!/usr/bin/env bash
# Make playwright evidence visible INLINE in the issue/PR comment.
#
# GitHub renders an inline video player only for human-uploaded user-attachments (no API),
# so a workflow cannot embed real video. What comments DO render inline is images from any
# URL. So per playwright leg we publish the leg's screenshot (<leg>.png — the key frame,
# persists past artifact expiry) to an orphan evidence branch and append it to comment.md
# via a raw.githubusercontent URL. The full video/trace/HTML report stay in the run
# artifacts. The branch is prunable at any time (old comments then lose images, like
# expired artifacts).
#
# Env: COMMENT (default comment.md), ART (default artifacts), BRANCH (evidence branch, req),
#      REPO ("owner/name", req), RUN_ID (req), TOKEN (push token, req unless PUSH=skip),
#      PUSH=skip (testing: append markdown only, no git push).
set -euo pipefail

COMMENT=${COMMENT:-comment.md}
ART=${ART:-artifacts}
: "${BRANCH:?BRANCH (evidence branch) is required}"
: "${REPO:?REPO is required}"
: "${RUN_ID:?RUN_ID is required}"

[ -f "$COMMENT" ] || { echo "::warning::$COMMENT not found — skipping inline evidence"; exit 0; }

OUT=$(mktemp -d)
LEGS=()
for d in "$ART"/repro-*/; do
  [ -d "$d" ] || continue # unmatched glob
  leg=$(basename "$d" | sed 's/^repro-//')
  png=$(find "$d" -name 'test-*.png' 2>/dev/null | head -1)
  [ -n "$png" ] || continue
  cp "$png" "$OUT/$leg.png"
  LEGS+=("$leg")
done
[ "${#LEGS[@]}" -gt 0 ] || { echo "no playwright evidence found — nothing to embed"; exit 0; }

if [ "${PUSH:-}" != "skip" ]; then
  : "${TOKEN:?TOKEN is required to push evidence}"
  EV=$(mktemp -d)
  git -C "$EV" init -q
  git -C "$EV" remote add origin "https://x-access-token:${TOKEN}@github.com/${REPO}.git"
  if git -C "$EV" fetch -q --depth 1 origin "$BRANCH" 2>/dev/null; then
    git -C "$EV" checkout -q FETCH_HEAD
  else
    git -C "$EV" checkout -q --orphan "$BRANCH"
    printf '# repro evidence\n\nInline images referenced by reproduce/fix-verify comments.\nSafe to prune at any time — old comments then lose their inline images\n(the full evidence was in the run artifacts, which expire anyway).\n' > "$EV/README.md"
    git -C "$EV" add README.md
  fi
  mkdir -p "$EV/runs/$RUN_ID"
  cp "$OUT"/* "$EV/runs/$RUN_ID/"
  git -C "$EV" add "runs/$RUN_ID"
  git -C "$EV" -c user.name=github-actions -c user.email=actions@github.com \
    commit -q -m "evidence for run $RUN_ID"
  git -C "$EV" push -q origin "HEAD:refs/heads/$BRANCH"
fi

RAW="https://raw.githubusercontent.com/$REPO/$BRANCH/runs/$RUN_ID"
{
  echo
  echo "### Evidence"
  for leg in "${LEGS[@]}"; do
    echo
    echo "**${leg}**"
    echo "![${leg} — final frame](${RAW}/${leg}.png)"
  done
  echo
  echo "_Full video, trace and the interactive Playwright HTML report are in the \`repro-*\` run artifacts (they expire after 7 days; the screenshots above persist)._"
} >> "$COMMENT"
echo "embedded inline evidence for: ${LEGS[*]}"
