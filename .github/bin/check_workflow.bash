#!/usr/bin/env bash
set -euo pipefail

if [[ -n "${DEBUG:-}" ]]; then
  set -x
fi

DATE=$(date --rfc-3339=date)

function count() {
  local repo; repo="${1}"
  local workflow; workflow="${2}"
  local status; status="${3}"

  gh -R "${repo}" run list \
    --workflow="${workflow}" \
    --created="${DATE}" \
    --status="${status}" \
    --json="headSha" \
    --jq="length"
}

if [[ "$(count "$@")" -gt 0 ]]; then
  exit 0
else
  exit 1
fi
