#!/usr/bin/env bash
# Assertion helpers for the shell-step tests.
set -euo pipefail

PASS=0
FAIL=0

# Assert that $2 (haystack) contains $1 (needle); $3 names the case in the output.
assert_contains() {
  if grep -qF -- "$1" <<<"$2"; then
    PASS=$((PASS + 1)); echo "  ok   $3"
  else
    FAIL=$((FAIL + 1)); echo "  FAIL $3"; echo "       expected to find: $1"; echo "       in: $2"
  fi
}

# Assert exact equality of $1 (expected) and $2 (actual); $3 names the case.
assert_equals() {
  if [ "$1" = "$2" ]; then
    PASS=$((PASS + 1)); echo "  ok   $3"
  else
    FAIL=$((FAIL + 1)); echo "  FAIL $3"; echo "       expected: $1"; echo "       actual:   $2"
  fi
}

finish() {
  echo "  ${PASS} passed, ${FAIL} failed"
  [ "$FAIL" -eq 0 ]
}
