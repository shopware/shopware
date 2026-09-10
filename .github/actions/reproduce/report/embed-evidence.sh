#!/usr/bin/env bash
# Publish Playwright evidence and describe it for the comment renderer. A bot comment can't embed a
# <video> player and inline images bloat it, so we push each leg's SCREENSHOT + RECORDING to an
# orphan evidence branch (they persist past the 7-day artifact expiry; prunable any time) and write
# a manifest — evidence.json — that comment.ts turns into per-leg spoilers + a recording link.
# Only playwright legs have screenshots.
#
# Env: ART(=artifacts), BRANCH(req), REPO(req), RUN_ID(req), OUT(=evidence.json),
#      TOKEN(req unless PUSH=skip), PUSH=skip (testing: write the manifest only, no git push).
set -euo pipefail

ART=${ART:-artifacts}; OUT=${OUT:-evidence.json}
: "${BRANCH:?BRANCH is required}"; : "${REPO:?REPO is required}"; : "${RUN_ID:?RUN_ID is required}"
# This script pushes to a dedicated evidence branch with a contents:write token — a fast-forward push
# with retry-on-race (see the loop below), NOT a force-push; an orphan root is created only on the
# first ever push. Refuse any branch other than the dedicated evidence namespace so a misconfig can
# never rewrite trunk/a release branch.
[ "$BRANCH" = "ci/repro-evidence" ] || { echo "::error::evidence branch must be ci/repro-evidence, got '$BRANCH'"; exit 1; }
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
trap 'rm -rf "$staged" "${repo:-}"' EXIT
for i in $(seq 0 $((n - 1))); do
  cp "${pngs[$i]}" "$staged/${names[$i]}.png"
  [ -n "${vids[$i]}" ] && cp "${vids[$i]}" "$staged/${names[$i]}.webm" || true
done

if [ "${PUSH:-}" != skip ]; then
  : "${TOKEN:?TOKEN is required to push evidence}"
  repo=$(mktemp -d)
  git -C "$repo" init -q
  git -C "$repo" remote add origin "https://x-access-token:${TOKEN}@github.com/${REPO}.git"

  # Each run publishes into its OWN runs/<RUN_ID>/ dir, so concurrent runs never write the same paths.
  # The only race is the branch tip advancing between our fetch and our push: a plain push is then
  # rejected (non-fast-forward) and — under set -e — this run's evidence would be silently lost, while a
  # blind --force would drop the concurrent run's dir. So on rejection we re-fetch the moved tip, replay
  # our run dir onto it, and retry. Since dirs never collide, a retry always fast-forwards cleanly.
  pushed=""
  for attempt in 1 2 3 4 5; do
    # Start each attempt from a clean slate so a re-fetch can't be blocked by the prior attempt's branch.
    git -C "$repo" checkout -q --detach 2>/dev/null || true
    git -C "$repo" branch -qD publish 2>/dev/null || true
    if git -C "$repo" fetch -q --depth 1 origin "$BRANCH" 2>/dev/null; then
      git -C "$repo" checkout -q -B publish FETCH_HEAD
    else
      # Branch doesn't exist yet — start a fresh orphan history (first evidence push ever).
      git -C "$repo" checkout -q --orphan publish
      git -C "$repo" rm -rfq . 2>/dev/null || true
      printf '# repro evidence\n\nImages/recordings referenced by reproduce comments. Safe to prune any time.\n' > "$repo/README.md"
      git -C "$repo" add README.md
    fi
    mkdir -p "$repo/runs/$RUN_ID"
    cp "$staged"/* "$repo/runs/$RUN_ID/"
    git -C "$repo" add "runs/$RUN_ID"
    git -C "$repo" -c user.name=github-actions -c user.email=actions@github.com commit -q -m "evidence for run $RUN_ID"
    if git -C "$repo" push -q origin "HEAD:refs/heads/$BRANCH"; then pushed=1; break; fi
    echo "evidence push rejected (attempt $attempt/5) — branch tip advanced; re-fetching and retrying"
    sleep "${EVIDENCE_RETRY_SLEEP:-1}"   # brief backoff; tests set 0 to keep the suite fast
  done
  [ -n "$pushed" ] || { echo "::error::failed to publish evidence for run $RUN_ID after 5 attempts (branch $BRANCH kept moving)"; exit 1; }
fi

# Manifest: one entry per leg with its raw URLs. comment.ts decides combined-vs-per-leg from the
# statuses, so we don't pre-collapse here.
echo '{"legs":[]}' > "$OUT"
for i in $(seq 0 $((n - 1))); do
  webm=""; [ -n "${vids[$i]}" ] && webm="$raw/${names[$i]}.webm"
  tmp=$(mktemp)
  jq --arg name "${names[$i]}" --arg status "${stats[$i]}" --arg png "$raw/${names[$i]}.png" --arg webm "$webm" \
    '.legs += [{name:$name, status:$status, png:$png, webm:$webm}]' "$OUT" > "$tmp" && mv "$tmp" "$OUT"
done
echo "published evidence for $n leg(s) → $OUT"
