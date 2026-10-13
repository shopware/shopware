#!/usr/bin/env bash
# Synthetic diff: deletes the deprecated public Twig function `sw_cover_url`
# (ProductCoverUrlExtension under src/Storefront) and adds an UPGRADE-6.8 entry.
# The deprecation cycle and the release docs are in place, but nothing states the
# measured ecosystem impact or whether a forwarding shim was considered.
# Per guides/bc-removal-before-major.md (REMOVAL-001..003): expect a REMOVAL-
# finding with requires_human, since keeping or removing is a human decision.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"     '.persona == "maintainer"'
require "removal-rule-finding"  '[(.findings // [])[] | select((.rule_id // "") | startswith("REMOVAL-"))] | length >= 1'
require "removal-requires-human" '[(.findings // [])[] | select(((.rule_id // "") | startswith("REMOVAL-")) and .requires_human == true)] | length >= 1'
check "removal-not-blocking"    '[(.findings // [])[] | select(((.rule_id // "") | startswith("REMOVAL-")) and .severity == "blocking")] | length == 0'

emit_result
