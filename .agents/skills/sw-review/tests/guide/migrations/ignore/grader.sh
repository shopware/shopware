#!/usr/bin/env bash
# Clean: base trunk, one additive 6.7 migration. The column is added with
# addColumn(), the index behind TableHelper::indexExists() through
# executeDdlStatement(), the data update only touches unmarked rows, the
# timestamp is exact, updateDestructive() is empty and untested, and the
# migration test runs update() twice. Guide migrations, "Do not flag".
# Expectation: no MIG- finding, nothing major or blocking.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check   "persona-attribution"  '.persona == "architecture"'
require "no-mig-finding"       '[(.findings // [])[] | select((.rule_id // "") | startswith("MIG-"))] | length == 0'
check   "no-major-or-blocking" '[(.findings // [])[] | select(.severity == "major" or .severity == "blocking")] | length == 0'

emit_result
