#!/usr/bin/env bash
# Plant: required prop entityName added to the public sw-media-field component
# and its "label" slot removed, no deprecation. Guide bc-admin (BCADM-001,
# BCADM-002). Expectation: a BCADM- finding with severity major or blocking.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "architecture"'
check "rule-finding"          '[(.findings // [])[] | select((.rule_id // "") | startswith("BCADM-"))] | length >= 1'
check "rule-severity"         '[(.findings // [])[] | select(((.rule_id // "") | startswith("BCADM-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'

emit_result
