#!/usr/bin/env bash
# Plant: admin twig adds button + hint with hardcoded English strings, no
# snippet key. Per personas/ux.md: "Admin snippets: no hard-coded user-facing
# strings". Expectation: major finding under maintainability/docs.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "ux"'
check "has-finding"           '(.findings // []) | length >= 1'
check "non-nit-severity"      '[(.findings // [])[] | select(.severity == "blocking" or .severity == "major" or .severity == "minor")] | length >= 1'

emit_result
