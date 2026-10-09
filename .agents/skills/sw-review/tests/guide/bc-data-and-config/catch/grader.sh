#!/usr/bin/env bash
# Plant: base trunk, V6_7 migration. update() makes the existing nullable column
# customer_address.phone_number NOT NULL (blue-green unsafe: the previous release
# still inserts addresses without a phone number), and the DAL field gets
# new Required(), so Admin/Store API writes without the field now fail. No flag,
# no release docs, no plan for the next major.
# Guide bc-data-and-config (BCDATA-001).
# Expectation: a BCDATA- finding with severity major or blocking.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "architecture"'
check "rule-finding"          '[(.findings // [])[] | select((.rule_id // "") | startswith("BCDATA-"))] | length >= 1'
check "rule-severity"         '[(.findings // [])[] | select(((.rule_id // "") | startswith("BCDATA-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'

# The planted expectation is decisive: when it fails, the score is 0 no matter
# how many schema checks pass (otherwise 7/8 would clear the 0.8 threshold).
GATE='[(.findings // [])[] | select(((.rule_id // "") | startswith("BCDATA-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'
if jq -e "$GATE" "$OUT" >/dev/null 2>&1; then
    emit_result
else
    emit_result | jq -c '.score = 0'
fi
