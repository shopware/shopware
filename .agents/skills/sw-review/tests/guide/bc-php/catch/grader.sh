#!/usr/bin/env bash
# Plant: base trunk. LineItemQuantitySplitter (non-internal) renames its public
# method split() to splitQuantity() and updates the core callers, with no
# forwarding old method, no @deprecated tag, no BC-change attribute and no
# RELEASE_INFO / UPGRADE entry. roave would report the removed method; the
# planted problem is the missing deprecation path and release docs.
# Guide bc-php (BCPHP-001, BCPHP-006).
# Expectation: a BCPHP- finding with severity major or blocking.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "architecture"'
check "rule-finding"          '[(.findings // [])[] | select((.rule_id // "") | startswith("BCPHP-"))] | length >= 1'
check "rule-severity"         '[(.findings // [])[] | select(((.rule_id // "") | startswith("BCPHP-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'

# The planted expectation is decisive: when it fails, the score is 0 no matter
# how many schema checks pass (otherwise 7/8 would clear the 0.8 threshold).
GATE='[(.findings // [])[] | select(((.rule_id // "") | startswith("BCPHP-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'
if jq -e "$GATE" "$OUT" >/dev/null 2>&1; then
    emit_result
else
    emit_result | jq -c '.score = 0'
fi
