#!/usr/bin/env bash
# Clean: base trunk. LineItemQuantitySplitter::split() gets a new required
# parameter the BC-safe way: #[NewRequiredParameter] attribute, func_get_arg()
# shim with Feature::triggerDeprecationOrThrow('v6.8.0.0') for legacy calls,
# the new rounding only inside the existing Feature::isActive('v6.8.0.0')
# branch, core callers updated, RELEASE_INFO and UPGRADE entries present. The
# constructor change is allowed (service constructors are @internal).
# Guide bc-php, "Do not flag".
# Expectation: no BCPHP-001, -002, -003 or -006 finding. A BCPHP-007 finding
# (check ecosystem usage) is legitimate on this diff and does not count.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check   "persona-attribution"   '.persona == "architecture"'
require "no-planted-bcphp"      '[(.findings // [])[] | select((.rule_id // "") as $r | ["BCPHP-001","BCPHP-002","BCPHP-003","BCPHP-006"] | index($r) != null)] | length == 0'
check   "no-blocking"           '[(.findings // [])[] | select(.severity == "blocking")] | length == 0'

emit_result
