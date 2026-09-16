#!/usr/bin/env bash
# Out-of-lens: SCSS brand colour tweak. open-source has no claim on visual.
# Expectation: zero findings.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "open-source"'
check "no-findings"           '(.findings // []) | length == 0'
check "decision-comment"      '.decision == "comment"'

emit_result
