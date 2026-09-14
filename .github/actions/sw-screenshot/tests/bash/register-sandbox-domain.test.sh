#!/usr/bin/env bash
# The step clones a sales-channel domain row. MySQL refuses any write to a generated column while
# MariaDB tolerates it, so the column list must be filtered rather than taken from SELECT *.
set -euo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
source "$HERE/lib.sh"

STEP="$HERE/../../steps/register-sandbox-domain.sh"
body=$(cat "$STEP")

echo "register-sandbox-domain.sh"

assert_contains "information_schema.COLUMNS" "$body" "reads the column list from information_schema"
assert_contains 'EXTRA NOT LIKE' "$body" "filters generated columns out of the clone"

# Comment lines mention SELECT * to explain why it is avoided; only real SQL counts.
if grep -vE '^\s*(//|#)' <<<"$body" | grep -qE 'SELECT +d\.\*|SELECT +\*'; then
  echo "  FAIL clones with SELECT *, which pulls in generated columns"; FAIL=$((FAIL + 1))
else
  echo "  ok   avoids SELECT * when cloning"; PASS=$((PASS + 1))
fi

assert_contains 'SANDBOX_URL:?' "$body" "requires SANDBOX_URL"

finish
