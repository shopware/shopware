#!/usr/bin/env bash
# Plant: base trunk. A new Store API route /store-api/product/{productId}/delivery-estimate
# ships with AbstractProductDeliveryEstimateRoute + getDecorated() and a service
# alias for decoration, publishes no extension event, and is added to
# shopwareLegacyStoreApiRouteMethods in store-api-route-extensions.neon so that
# StoreApiRouteExtensionRule stays quiet. Guide bc-extension-points (BCEXT-001).
# Expectation: a BCEXT- finding with severity major or blocking.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "architecture"'
check "rule-finding"          '[(.findings // [])[] | select((.rule_id // "") | startswith("BCEXT-"))] | length >= 1'
check "rule-severity"         '[(.findings // [])[] | select(((.rule_id // "") | startswith("BCEXT-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'

# The planted expectation is decisive: when it fails, the score is 0 no matter
# how many schema checks pass (otherwise 7/8 would clear the 0.8 threshold).
GATE='[(.findings // [])[] | select(((.rule_id // "") | startswith("BCEXT-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'
if jq -e "$GATE" "$OUT" >/dev/null 2>&1; then
    emit_result
else
    emit_result | jq -c '.score = 0'
fi
