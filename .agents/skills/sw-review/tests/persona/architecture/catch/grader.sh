#!/usr/bin/env bash
# Plant: repository->search() called inside foreach over cart line items.
# Per personas/architecture.md: "Hot paths ... avoid N+1, sync I/O in loops".
# Expectation: at least one finding categorised performance OR maintainability.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"     '.persona == "architecture"'
check "has-finding"             '(.findings // []) | length >= 1'
check "perf-or-maint-category"  '[(.findings // [])[] | select(.category == "performance" or .category == "maintainability" or .category == "correctness")] | length >= 1'
check "non-nit-severity"        '[(.findings // [])[] | select(.severity == "blocking" or .severity == "major" or .severity == "minor")] | length >= 1'

emit_result
