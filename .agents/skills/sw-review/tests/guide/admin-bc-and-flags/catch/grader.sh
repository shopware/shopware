#!/usr/bin/env bash
# Plant: base trunk, Administration customer detail page. A new computed
# property checks `ACCESSIBILITY_TWEAKS` inside a `V6_8_0_0` branch: a nested
# flag check (ADMF-003) with the env-style uppercase spelling of the major
# flag (ADMF-001). The template drops the
# `sw-customer-detail__before-content` extension section and the PR removes
# the entry from src/meta/position-identifiers.json, so the meta Jest test
# passes while app content on that screen disappears (ADMF-008).
# Guide admin-bc-and-flags.
# Expectation: an ADMF- finding; findings on the identifier removal and on the
# flag checks are expected but not decisive on their own.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

PI="src/Administration/Resources/app/administration/src/meta/position-identifiers.json"
TWIG="src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-detail/sw-customer-detail.html.twig"
JS="src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-detail/index.js"

check   "persona-attribution"         '.persona == "ux"'
require "admf-rule-finding"           '[(.findings // [])[] | select((.rule_id // "") | startswith("ADMF-"))] | length >= 1'
check   "position-identifier-finding" "[(.findings // [])[] | select(((.rule_id // \"\") | startswith(\"ADMF-\")) and (.file == \"$PI\" or .file == \"$TWIG\"))] | length >= 1"
check   "flag-finding"                "[(.findings // [])[] | select(((.rule_id // \"\") | startswith(\"ADMF-\")) and .file == \"$JS\")] | length >= 1"
check   "major-or-higher"             '[(.findings // [])[] | select(((.rule_id // "") | startswith("ADMF-")) and (.severity == "major" or .severity == "blocking"))] | length >= 1'

emit_result
