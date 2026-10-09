#!/usr/bin/env bash
# Clean: base trunk. LineItemQuantitySplitter::split() gets a new required
# parameter the BC-safe way: #[NewRequiredParameter] attribute, func_get_arg()
# shim with Feature::triggerDeprecationOrThrow('v6.8.0.0') for legacy calls,
# the new rounding only inside the existing Feature::isActive('v6.8.0.0')
# branch, core callers updated, RELEASE_INFO and UPGRADE entries present. The
# constructor change is allowed (service constructors are @internal).
# Guide bc-php, "Do not flag".
# Expectation: no BCPHP- finding.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "architecture"'
check "no-bcphp-finding"      '[(.findings // [])[] | select((.rule_id // "") | startswith("BCPHP-"))] | length == 0'
check "no-blocking"           '[(.findings // [])[] | select(.severity == "blocking")] | length == 0'

# The planted expectation is decisive: when it fails, the score is 0 no matter
# how many schema checks pass (otherwise 7/8 would clear the 0.8 threshold).
GATE='[(.findings // [])[] | select((.rule_id // "") | startswith("BCPHP-"))] | length == 0'
if jq -e "$GATE" "$OUT" >/dev/null 2>&1; then
    emit_result
else
    emit_result | jq -c '.score = 0'
fi
