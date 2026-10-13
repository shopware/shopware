#!/usr/bin/env bash
# Real diff: a change moved theme-folder cleanup from DeleteThemeFilesTaskHandler
# into theme:compile / theme:change because the scheduled task "does not run
# reliably in some environments". Outcome: cached storefront pages requested
# CSS/JS from deleted folders and got 404s.
# Guides loaded: platform-scope (SCOPE-001) and cache-and-asset-lifetime (CACHE-001..003).
# Expectation: a SCOPE- or CACHE- finding, major or blocking, category scope or
# correctness; the scope finding carries requires_human.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"        '.persona == "maintainer"'
require "guide-rule-finding"       '[(.findings // [])[] | select((.rule_id // "") | test("^(SCOPE|CACHE)-"))] | length >= 1'
require "major-or-blocking"        '[(.findings // [])[] | select(((.rule_id // "") | test("^(SCOPE|CACHE)-")) and (.severity == "major" or .severity == "blocking"))] | length >= 1'
check "scope-or-correctness"       '[(.findings // [])[] | select(((.rule_id // "") | test("^(SCOPE|CACHE)-")) and (.category == "scope" or .category == "correctness"))] | length >= 1'
require "scope-requires-human"     '[(.findings // [])[] | select(.category == "scope" or ((.rule_id // "") | startswith("SCOPE-")))] | all(.requires_human == true)'

emit_result
