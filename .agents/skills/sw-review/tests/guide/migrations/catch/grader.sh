#!/usr/bin/env bash
# Plant: base trunk, two new 6.7 migrations. Migration1791537318 drops
# `product_sorting.locked` in update(), inside an `if` block, so PHPStan
# NoDropStatementInUpdateRule (top-level statements only) does not see it; the
# old release still reads the column during a blue-green deployment.
# Migration1791537247 adds an index with a raw ALTER TABLE and no indexExists()
# guard, so a re-run after a timeout fails; its test runs update() only once.
# Guide migrations (MIG-002, MIG-001, MIG-010).
# Expectation: a MIG- finding with severity major or blocking.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check   "persona-attribution"   '.persona == "architecture"'
require "mig-rule-finding"      '[(.findings // [])[] | select((.rule_id // "") | startswith("MIG-"))] | length >= 1'
require "mig-major-or-blocking" '[(.findings // [])[] | select(((.rule_id // "") | startswith("MIG-")) and (.severity == "blocking" or .severity == "major"))] | length >= 1'
check   "drop-in-update-found"  '[(.findings // [])[] | select(.file == "src/Core/Migration/V6_7/Migration1791537318RemoveLockedFromProductSorting.php")] | length >= 1'
check   "idempotency-found"     '[(.findings // [])[] | select(.file == "src/Core/Migration/V6_7/Migration1791537247AddIsSystemToProductSorting.php" or .file == "tests/migration/Core/V6_7/Migration1791537247AddIsSystemToProductSortingTest.php")] | length >= 1'

emit_result
