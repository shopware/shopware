#!/usr/bin/env bash
# Plant: Storefront Twig block component_pagination_item_link_text renamed and
# the public paginationNavSelector option removed from ListingPaginationPlugin,
# both without deprecation. Guide bc-storefront (BCSF-001, BCSF-005).
# Expectation: a BCSF- finding with severity major or blocking.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "ux"'
check "rule-finding"          '[(.findings // [])[] | select((.rule_id // "") | startswith("BCSF-"))] | length >= 1'
check "rule-severity"         '[(.findings // [])[] | select(((.rule_id // "") | startswith("BCSF-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'

emit_result
