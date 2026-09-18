#!/usr/bin/env bash
# Runs every tests/bash/*.test.sh and fails if any of them fails. Invoked by `npm run test:bash`.
set -u
here=$(cd "$(dirname "$0")" && pwd)
fail=0
shopt -s nullglob
for f in "$here"/*.test.sh; do
  echo "== $(basename "$f") =="
  bash "$f" || fail=1
done
if [ "$fail" -eq 0 ]; then
  echo "bash tests: all passed"
else
  echo "bash tests: FAILURES"
  exit 1
fi
