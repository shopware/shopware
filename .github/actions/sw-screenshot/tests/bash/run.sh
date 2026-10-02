#!/usr/bin/env bash
# Run every shell-step test.
set -euo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
status=0

for test in "$HERE"/*.test.sh; do
  bash "$test" || status=1
done

exit "$status"
