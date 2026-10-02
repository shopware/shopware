#!/usr/bin/env bash
# The pull request's head ref lives in the repository running the workflow. On a fork that is not the
# repository Shopware was cloned from, so fetching from the shop checkout's origin silently misses it
# and the "after" screenshot can never be produced.
set -euo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
source "$HERE/lib.sh"

STEP="$HERE/../../steps/swap.sh"
body=$(cat "$STEP")

echo "swap.sh"

assert_contains 'PR_REPO:?' "$body" "requires the pull request repository"
assert_contains 'https://github.com/${PR_REPO}.git' "$body" "fetches from that repository by url"

if grep -vE '^\s*#' <<<"$body" | grep -qE 'git fetch[^|]*\borigin\b'; then
  echo "  FAIL still fetches the pull request ref from the shop checkout origin"; FAIL=$((FAIL + 1))
else
  echo "  ok   does not rely on the shop checkout origin"; PASS=$((PASS + 1))
fi

assert_contains 'database:migrate --all' "$body" "runs migrations after moving the source"

# `composer build:js:admin` runs `npm run build` and no install of its own, so a head that adds a
# dependency would build against the merge base's node_modules.
assert_contains 'src/Administration/Resources/app/administration && npm ci' "$body" \
  "installs admin dependencies when the head moved them"

finish
