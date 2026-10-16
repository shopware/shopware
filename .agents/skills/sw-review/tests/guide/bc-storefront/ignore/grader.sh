#!/usr/bin/env bash
# Clean: inner markup of component_pagination_item_link_text changes while the
# block keeps its name and position; the private _resumeFocusState() helper of
# ListingPaginationPlugin is inlined and removed. Guide bc-storefront lists both
# under "Do not flag". Expectation: no BCSF- finding.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "ux"'
check "no-rule-finding"       '[(.findings // [])[] | select((.rule_id // "") | startswith("BCSF-"))] | length == 0'
check "no-blocking"           '[(.findings // [])[] | select(.severity == "blocking")] | length == 0'

emit_result
