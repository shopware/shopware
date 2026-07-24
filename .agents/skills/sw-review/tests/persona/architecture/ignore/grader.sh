#!/usr/bin/env bash
# Out-of-lens: admin product snippet copy tweak. Architecture has no claim.
# Expectation: zero findings.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "architecture"'
check "no-findings"           '(.findings // []) | length == 0'
check "decision-comment"      '.decision == "comment"'

emit_result
