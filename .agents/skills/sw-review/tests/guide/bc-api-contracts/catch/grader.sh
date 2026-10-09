#!/usr/bin/env bash
# Plant: /store-api/handle-payment renames response field redirectUrl to
# paymentRedirectUrl and reads a new request header sw-payment-finish-url,
# no OpenAPI schema change. Guide bc-api-contracts (BCAPI-001, BCAPI-003,
# BCAPI-004). Expectation: a BCAPI- finding with severity major or blocking.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "architecture"'
check "rule-finding"          '[(.findings // [])[] | select((.rule_id // "") | startswith("BCAPI-"))] | length >= 1'
check "rule-severity"         '[(.findings // [])[] | select(((.rule_id // "") | startswith("BCAPI-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'

emit_result
