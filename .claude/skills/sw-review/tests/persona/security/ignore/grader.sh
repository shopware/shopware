#!/usr/bin/env bash
# Out-of-lens: storefront snippet copy tweak. Security persona has no claim on
# i18n strings. Expectation: zero findings.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "security"'
check "no-findings"           '(.findings // []) | length == 0'
check "decision-comment"      '.decision == "comment"'

emit_result
