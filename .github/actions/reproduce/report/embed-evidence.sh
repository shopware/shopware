#!/usr/bin/env bash
# Publish Playwright evidence and describe it for the comment renderer. A bot comment can't embed a
# <video> player and inline images bloat it, so we push each leg's SCREENSHOT + RECORDING to an
# orphan evidence branch (they persist past the 7-day artifact expiry; prunable any time) and write
# a manifest — evidence.json — that comment.mjs turns into per-leg spoilers + a recording link.
# Only playwright legs have screenshots.
#
# Env: ART(=artifacts), BRANCH(req), REPO(req), RUN_ID(req), OUT(=evidence.json),
#      TOKEN(req unless PUSH=skip), PUSH=skip (testing: write the manifest only, no git push).
set -euo pipefail

ART=${ART:-artifacts}; OUT=${OUT:-evidence.json}
: "${BRANCH:?BRANCH is required}"; : "${REPO:?REPO is required}"; : "${RUN_ID:?RUN_ID is required}"
raw="https://raw.githubusercontent.com/$REPO/$BRANCH/runs/$RUN_ID"

# Gather playwright legs that produced a screenshot (parallel arrays: name / png / webm / status).
names=(); pngs=(); vids=(); stats=()
for dir in "$ART"/repro-*/; do
  [ -d "$dir" ] || continue
  [ "$(jq -r '.executor // ""' "$dir/result.json" 2>/dev/null)" = playwright ] || continue
  png=$(find "$dir" -name 'test-*.png' 2>/dev/null | head -1); [ -n "$png" ] || continue
  vid=$(find "$dir" -name '*.webm' 2>/dev/null | head -1 || true)
  names+=("$(basename "$dir" | sed 's/^repro-//')"); pngs+=("$png"); vids+=("$vid")
  stats+=("$(jq -r '.status // "?"' "$dir/result.json" 2>/dev/null || echo '?')")
done
n=${#names[@]}
if [ "$n" -eq 0 ]; then echo '{"legs":[]}' > "$OUT"; echo "no playwright evidence"; exit 0; fi

staged=$(mktemp -d)
for i in $(seq 0 $((n - 1))); do
  cp "${pngs[$i]}" "$staged/${names[$i]}.png"
  [ -n "${vids[$i]}" ] && cp "${vids[$i]}" "$staged/${names[$i]}.webm" || true
done

if [ "${PUSH:-}" != skip ]; then
  : "${TOKEN:?TOKEN is required to push evidence}"
  repo=$(mktemp -d)
  git -C "$repo" init -q
  git -C "$repo" remote add origin "https://x-access-token:${TOKEN}@github.com/${REPO}.git"
  if git -C "$repo" fetch -q --depth 1 origin "$BRANCH" 2>/dev/null; then
    git -C "$repo" checkout -q FETCH_HEAD
  else
    git -C "$repo" checkout -q --orphan "$BRANCH"
    printf '# repro evidence\n\nImages/recordings referenced by reproduce comments. Safe to prune any time.\n' > "$repo/README.md"
    git -C "$repo" add README.md
  fi
  mkdir -p "$repo/runs/$RUN_ID"
  cp "$staged"/* "$repo/runs/$RUN_ID/"
  git -C "$repo" add "runs/$RUN_ID"
  git -C "$repo" -c user.name=github-actions -c user.email=actions@github.com commit -q -m "evidence for run $RUN_ID"
  git -C "$repo" push -q origin "HEAD:refs/heads/$BRANCH"
fi

# Manifest: one entry per leg with its raw URLs. comment.mjs decides combined-vs-per-leg from the
# statuses, so we don't pre-collapse here.
echo '{"legs":[]}' > "$OUT"
for i in $(seq 0 $((n - 1))); do
  webm=""; [ -n "${vids[$i]}" ] && webm="$raw/${names[$i]}.webm"
  tmp=$(mktemp)
  jq --arg name "${names[$i]}" --arg status "${stats[$i]}" --arg png "$raw/${names[$i]}.png" --arg webm "$webm" \
    '.legs += [{name:$name, status:$status, png:$png, webm:$webm}]' "$OUT" > "$tmp" && mv "$tmp" "$OUT"
done
echo "published evidence for $n leg(s) → $OUT"
