#!/usr/bin/env bash
# Plant: SQL string concatenation in DBAL query (no bound parameters).
# Per personas/security.md: "SQL/DBAL: no interpolated variables; use bound parameters."
# Expectation: blocking finding under category=security.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "security"'
check "has-finding"           '(.findings // []) | length >= 1'
check "security-category"     '[(.findings // [])[] | select(.category == "security")] | length >= 1'
check "blocking-severity"     '[(.findings // [])[] | select(.severity == "blocking")] | length >= 1'

emit_result
