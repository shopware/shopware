#!/usr/bin/env bash
# Plant: base trunk. A bug fix in ProductCartProcessor (purchase steps below 1
# are treated as 1, fixQuantity() never goes below the minimum purchase) with
# no test change at all. The PR fixes an issue (metadata fixes_issue: true).
# Danger MissingUnitTests only covers new src files, so nothing in CI asks for
# the test. Guide tests-for-behavior-change (TEST-001).
# Expectation: a TEST- finding with severity major or blocking on the processor.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check   "persona-attribution"   '.persona == "architecture"'
require "test-rule-finding"     '[(.findings // [])[] | select((.rule_id // "") | startswith("TEST-"))] | length >= 1'
require "test-major-or-higher"  '[(.findings // [])[] | select(((.rule_id // "") | startswith("TEST-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'
check   "on-changed-file"       '[(.findings // [])[] | select(((.rule_id // "") | startswith("TEST-")) and .file == "src/Core/Content/Product/Cart/ProductCartProcessor.php")] | length >= 1'
check   "category-tests"        '[(.findings // [])[] | select(((.rule_id // "") | startswith("TEST-")) and .category == "tests")] | length >= 1'

emit_result
