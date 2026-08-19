#!/usr/bin/env bash
# Lint and format-check GitHub Actions workflows and composite actions.
#
#   .github/bin/lint-actions.bash          # lint (actionlint) + format check (yamlfmt -lint)
#   .github/bin/lint-actions.bash --fix    # apply yamlfmt formatting, then lint
#
# Tools: actionlint (https://github.com/rhysd/actionlint) and
#        yamlfmt (https://github.com/google/yamlfmt).
# When run locally without the tools installed it prints an install hint and
# skips (exit 0). In CI (CI=true) missing tools are a hard error, so the check
# cannot silently pass.
set -euo pipefail

if [ -n "${DEBUG:-}" ]; then
  set -x
fi

FIX=0
if [ "${1:-}" = "--fix" ]; then
  FIX=1
fi

missing=()
command -v actionlint >/dev/null 2>&1 || missing+=("actionlint")
command -v yamlfmt >/dev/null 2>&1 || missing+=("yamlfmt")
command -v zizmor >/dev/null 2>&1 || missing+=("zizmor")

if [ ${#missing[@]} -gt 0 ]; then
  echo "Skipping GitHub Actions lint: missing tool(s): ${missing[*]}"
  echo "Install them to enable this check, e.g.:"
  echo "  brew install actionlint yamlfmt zizmor  # macOS"
  echo "  go install github.com/rhysd/actionlint/cmd/actionlint@latest"
  echo "  go install github.com/google/yamlfmt/cmd/yamlfmt@latest"
  echo "  cargo install zizmor"
  if [ "${CI:-}" = "true" ]; then
    echo "Error: these tools are required in CI." >&2
    exit 1
  fi
  exit 0
fi

if [ "$FIX" -eq 1 ]; then
  echo "Formatting workflows with yamlfmt"
  yamlfmt
else
  echo "Checking workflow formatting with yamlfmt"
  yamlfmt -lint
fi

echo "Linting workflows with actionlint"
actionlint

# Audits configured in .github/zizmor.yml — currently `unpinned-uses` only.
# zizmor exits 0 only when it collected inputs and found nothing: 11-14 signal
# findings, 3 signals "no inputs collected", so a mistyped path fails instead of
# passing silently.
echo "Auditing workflows with zizmor"
# -q silences the per-file INFO progress lines; findings still print.
#
# zizmor exits 0 only when it collected inputs and found nothing: 11-14 signal
# findings and 3 signals "no inputs collected", so a mistyped path fails instead
# of passing silently. It does, however, exit 0 after *warning* about a file it
# could not parse — and an unparsed file is an unaudited file, so the warnings
# are captured and reconciled by zizmor-collection-guard.ts below.
zizmor_log="$(mktemp)"
trap 'rm -f "$zizmor_log"' EXIT

# Expected non-zero: findings. Captured rather than discarded so the guard still
# runs and the original code is returned afterwards.
zizmor_status=0
zizmor -q . 2>"$zizmor_log" || zizmor_status=$?
cat "$zizmor_log" >&2

node .github/bin/js/zizmor-collection-guard.ts "$zizmor_log"

if [ "$zizmor_status" -ne 0 ]; then
  exit "$zizmor_status"
fi
