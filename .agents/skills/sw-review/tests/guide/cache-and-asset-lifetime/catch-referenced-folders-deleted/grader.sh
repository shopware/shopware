#!/usr/bin/env bash
# Real diff: a change deletes unused theme/<hash> folders from theme:compile and
# theme:change, with the 24-hour grace period measured from the files' mtime.
# Outcome: cached pages still referenced the deleted folders, so their CSS/JS
# returned 404.
# Guide loaded: cache-and-asset-lifetime only.
# Expectation: a CACHE- finding on the command or the deleter, major or blocking.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

FILES='["src/Storefront/Theme/Command/ThemeCompileCommand.php","src/Storefront/Theme/Command/ThemeChangeCommand.php","src/Storefront/Theme/UnusedThemeDirectoryDeleter.php"]'

check "persona-attribution"   '.persona == "architecture"'
require "cache-rule-finding"  '[(.findings // [])[] | select((.rule_id // "") | startswith("CACHE-"))] | length >= 1'
require "cache-on-cleanup-file" "[(.findings // [])[] | select(((.rule_id // \"\") | startswith(\"CACHE-\")) and ((.file // \"\") as \$f | $FILES | index(\$f) != null))] | length >= 1"
check "cache-major-or-block"  '[(.findings // [])[] | select(((.rule_id // "") | startswith("CACHE-")) and (.severity == "major" or .severity == "blocking"))] | length >= 1'

emit_result
