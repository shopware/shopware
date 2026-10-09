#!/usr/bin/env bash
# Plant: base trunk, a new config option `shopware.sitemap.max_urls_per_file`.
# The RELEASE_INFO entry is added under `# 6.7.14.2`, a released section,
# instead of `# 6.7.16.0 (upcoming)`; the PR has no milestone label, so the
# release-info/section check reports nothing. The UPGRADE-6.8 entry is written
# in future tense ("We will lower ...") and spends most of its text on the
# reasoning. Guide release-docs (DOCS-003, DOCS-004, DOCS-005).
# Expectation: a DOCS- finding on RELEASE_INFO-6.7.md; a DOCS- finding on
# UPGRADE-6.8.md is expected but not decisive.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check   "persona-attribution"      '.persona == "maintainer"'
require "docs-rule-finding"        '[(.findings // [])[] | select((.rule_id // "") | startswith("DOCS-"))] | length >= 1'
require "released-section-finding" '[(.findings // [])[] | select(((.rule_id // "") | startswith("DOCS-")) and .file == "RELEASE_INFO-6.7.md")] | length >= 1'
check   "upgrade-wording-finding"  '[(.findings // [])[] | select(((.rule_id // "") | startswith("DOCS-")) and .file == "UPGRADE-6.8.md")] | length >= 1'
check   "docs-not-blocking"        '[(.findings // [])[] | select(((.rule_id // "") | startswith("DOCS-")) and .severity == "blocking")] | length == 0'

emit_result
