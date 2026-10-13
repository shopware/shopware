#!/usr/bin/env bash
# Plant: new file under changelog/_unreleased/. Per personas/maintainer.md:
# "changelog/_unreleased/ is legacy; new files there are wrong."
# Expectation: at least one finding under docs.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "maintainer"'
require "has-finding"           '(.findings // []) | length >= 1'
check "docs-category"         '[(.findings // [])[] | select(.category == "docs")] | length >= 1'

emit_result
