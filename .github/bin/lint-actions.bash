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

if [ ${#missing[@]} -gt 0 ]; then
  echo "Skipping GitHub Actions lint: missing tool(s): ${missing[*]}"
  echo "Install them to enable this check, e.g.:"
  echo "  brew install actionlint yamlfmt        # macOS"
  echo "  go install github.com/rhysd/actionlint/cmd/actionlint@latest"
  echo "  go install github.com/google/yamlfmt/cmd/yamlfmt@latest"
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
