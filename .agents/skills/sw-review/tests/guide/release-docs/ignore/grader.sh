#!/usr/bin/env bash
# Clean: base trunk. A plain bug fix in ProductCartProcessor (purchase steps
# below 1 no longer divide by zero) with a unit test, and no RELEASE_INFO or
# UPGRADE entry. Bug fixes without a contract change get no entry; Danger
# MissingReleaseInfo already prints its generic reminder.
# Guide release-docs (DOCS-001, "Do not flag").
# Expectation: no DOCS- finding and no docs-category finding.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check   "persona-attribution"  '.persona == "maintainer"'
require "no-docs-rule-finding" '[(.findings // [])[] | select((.rule_id // "") | startswith("DOCS-"))] | length == 0'
check   "no-docs-category"     '[(.findings // [])[] | select(.category == "docs")] | length == 0'
check   "no-blocking"          '[(.findings // [])[] | select(.severity == "blocking")] | length == 0'

emit_result
