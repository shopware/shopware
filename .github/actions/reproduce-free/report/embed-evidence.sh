#!/usr/bin/env bash
# Publish each leg's $EVIDENCE_DIR files and describe them for the comment renderer. A bot comment
# can't attach files, so everything a run dropped into evidence/ is pushed to the orphan evidence
# branch (persists past the 7-day artifact expiry; prunable any time) and listed in evidence.json —
# which render-comment.mjs resolves `{{evidence:<leg>:<file>}}` placeholders against. Captions come
# from the leg's result.json (`##repro evidence` markers).
#
# Env: ART(=artifacts), BRANCH(req), REPO(req), RUN_ID(req), OUT(=evidence.json),
#      TOKEN(req unless PUSH=skip), PUSH=skip (testing: write the manifest only, no git push).
set -euo pipefail

ART=${ART:-artifacts}; OUT=${OUT:-evidence.json}
: "${BRANCH:?BRANCH is required}"; : "${REPO:?REPO is required}"; : "${RUN_ID:?RUN_ID is required}"
raw="https://raw.githubusercontent.com/$REPO/$BRANCH/runs/$RUN_ID"

staged=$(mktemp -d)
trap 'rm -rf "$staged" "${repo:-}"' EXIT

echo '{"legs":[]}' > "$OUT"
count=0
for leg in reported trunk; do
  dir="$ART/repro-$leg/evidence"
  [ -d "$dir" ] || continue
  legfiles=$(find "$dir" -maxdepth 1 -type f | sort)
  [ -n "$legfiles" ] || continue

  mkdir -p "$staged/$leg"
  tmp=$(mktemp)
  jq --arg name "$leg" '.legs += [{name:$name, files:[]}]' "$OUT" > "$tmp" && mv "$tmp" "$OUT"
  while IFS= read -r f; do
    base=$(basename "$f")
    cp "$f" "$staged/$leg/$base"
    caption=$(jq -r --arg f "$base" '.evidence[]? | select(.file == $f) | .caption // ""' \
      "$ART/repro-$leg/result.json" 2>/dev/null | head -1 || true)
    tmp=$(mktemp)
    jq --arg name "$leg" --arg file "$base" --arg url "$raw/$leg/$base" --arg caption "$caption" \
      '(.legs[] | select(.name == $name) | .files) += [{file:$file, url:$url, caption:$caption}]' \
      "$OUT" > "$tmp" && mv "$tmp" "$OUT"
    count=$((count + 1))
  done <<< "$legfiles"
done

if [ "$count" -eq 0 ]; then echo "no evidence to publish"; exit 0; fi

if [ "${PUSH:-}" != skip ]; then
  : "${TOKEN:?TOKEN is required to push evidence}"
  repo=$(mktemp -d)
  git -C "$repo" init -q
  git -C "$repo" remote add origin "https://x-access-token:${TOKEN}@github.com/${REPO}.git"
  if git -C "$repo" fetch -q --depth 1 origin "$BRANCH" 2>/dev/null; then
    git -C "$repo" checkout -q FETCH_HEAD
  else
    git -C "$repo" checkout -q --orphan "$BRANCH"
    printf '# repro evidence\n\nFiles referenced by reproduce comments. Safe to prune any time.\n' > "$repo/README.md"
    git -C "$repo" add README.md
  fi
  mkdir -p "$repo/runs/$RUN_ID"
  cp -R "$staged"/. "$repo/runs/$RUN_ID/"
  git -C "$repo" add "runs/$RUN_ID"
  git -C "$repo" -c user.name=github-actions -c user.email=actions@github.com commit -q -m "evidence for run $RUN_ID"
  git -C "$repo" push -q origin "HEAD:refs/heads/$BRANCH"
fi

echo "published $count evidence file(s) → $OUT"
