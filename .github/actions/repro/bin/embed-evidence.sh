#!/usr/bin/env bash
# Make playwright evidence visible INLINE in the issue/PR comment.
#
# GitHub renders an inline video player only for human-uploaded user-attachments (no API),
# so a workflow cannot embed real video. What comments DO render inline is images from any
# URL — including animated GIFs. So per playwright leg we publish:
#   <leg>.png — the leg's final screenshot (key frame, persists past artifact expiry)
#   <leg>.gif — the SYMPTOM TAIL of the recording (last ~10s, 1.5x, 640px — a long flow
#               stays small because we always clip the tail, never the full run)
# to an orphan evidence branch and append them to comment.md via raw.githubusercontent URLs.
# The branch is prunable at any time (old comments then lose images, like expired artifacts).
#
# Env: COMMENT (default comment.md), ART (default artifacts), BRANCH (evidence branch, req),
#      REPO ("owner/name", req), RUN_ID (req), TOKEN (push token, req unless PUSH=skip),
#      PUSH=skip (testing: convert + append markdown, no git push).
set -euo pipefail

COMMENT=${COMMENT:-comment.md}
ART=${ART:-artifacts}
: "${BRANCH:?BRANCH (evidence branch) is required}"
: "${REPO:?REPO is required}"
: "${RUN_ID:?RUN_ID is required}"

[ -f "$COMMENT" ] || { echo "::warning::$COMMENT not found — skipping inline evidence"; exit 0; }
# ffmpeg is only needed for the tail GIF; without it we still embed the screenshot.
HAVE_FFMPEG=0; command -v ffmpeg >/dev/null && HAVE_FFMPEG=1
[ "$HAVE_FFMPEG" = 1 ] || echo "::warning::ffmpeg not available — embedding screenshots only (no recording GIF)"

OUT=$(mktemp -d)
LEGS=()
for d in "$ART"/repro-*/; do
  [ -d "$d" ] || continue # unmatched glob
  leg=$(basename "$d" | sed 's/^repro-//')
  png=$(find "$d" -name 'test-*.png' 2>/dev/null | head -1)
  webm=$(find "$d" -name 'video.webm' 2>/dev/null | head -1)
  [ -n "$png$webm" ] || continue
  [ -n "$png" ] && cp "$png" "$OUT/$leg.png"
  if [ -n "$webm" ] && [ "$HAVE_FFMPEG" = 1 ]; then
    # Tail clip only: the symptom moment is at the END of the recording.
    ffmpeg -y -v error -sseof -10 -i "$webm" \
      -vf "setpts=PTS/1.5,fps=8,scale=640:-1:flags=lanczos" "$OUT/$leg.gif" || true
  fi
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
    [ -f "$OUT/$leg.gif" ] && echo "![${leg} — symptom recording (tail)](${RAW}/${leg}.gif)"
    [ -f "$OUT/$leg.png" ] && echo "![${leg} — final frame](${RAW}/${leg}.png)"
  done
  echo
  echo "_Full video, trace and the interactive Playwright HTML report are in the \`repro-*\` run artifacts (they expire after 7 days; the images above persist)._"
} >> "$COMMENT"
echo "embedded inline evidence for: ${LEGS[*]}"
