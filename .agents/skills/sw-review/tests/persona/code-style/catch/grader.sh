#!/usr/bin/env bash
# Plant: method `findBenutzerByEmail`, parameter `$benutzer`, German comment in
# an otherwise English file. Per personas/code-style.md: "Mixed German/English
# identifiers or public docs." Expectation: at least one minor finding.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "code-style"'
check "has-finding"           '(.findings // []) | length >= 1'
check "maint-category"        '[(.findings // [])[] | select(.category == "maintainability")] | length >= 1'
check "no-blocking"           '[(.findings // [])[] | select(.severity == "blocking")] | length == 0'

emit_result
