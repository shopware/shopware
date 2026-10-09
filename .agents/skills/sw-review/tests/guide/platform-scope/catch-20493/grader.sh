#!/usr/bin/env bash
# Real diff: PR #20493 streamed private downloads through PHP for every shop
# because one hosting setup had a storage endpoint the browser could not reach.
# Closed in favour of documenting the requirement.
# Expectation: a SCOPE- finding (SCOPE-002, options / changed default for one
# setup), severity major, category scope, requires_human.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "maintainer"'
require "scope-rule-finding"  '[(.findings // [])[] | select((.rule_id // "") | startswith("SCOPE-"))] | length >= 1'
check "scope-major"           '[(.findings // [])[] | select(((.rule_id // "") | startswith("SCOPE-")) and .severity == "major")] | length >= 1'
check "scope-category"        '[(.findings // [])[] | select(((.rule_id // "") | startswith("SCOPE-")) and .category == "scope")] | length >= 1'
check "scope-requires-human"  '[(.findings // [])[] | select(.category == "scope" or ((.rule_id // "") | startswith("SCOPE-")))] | all(.requires_human == true)'

emit_result
