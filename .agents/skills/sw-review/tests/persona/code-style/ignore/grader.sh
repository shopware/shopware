#!/usr/bin/env bash
# Out-of-lens: composer.json dependency bump. code-style does not own deps.
# Expectation: zero findings.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "code-style"'
check "no-findings"           '(.findings // []) | length == 0'
check "decision-comment"      '.decision == "comment"'

emit_result
