#!/usr/bin/env bash
# Tests the offline, deterministic paths of steps/resolve-version.sh: version extraction from the
# issue body and the trunk fallback. Network-dependent paths (underspecified versions resolved via
# gh/git tag listings) are intentionally out of scope here.
set -u
here=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib.sh
. "$here/lib.sh"
STEPS=$(cd "$here/../../steps" && pwd)

# Run resolve-version.sh in a throwaway dir with the given issue body, stubbing gh/git to fail so the
# tag/date lookups are skipped and only the offline logic runs. Echoes the KEY=VALUE output.
run_resolve() { # body [extra env assignments...]
  local body=$1; shift
  local d; d=$(mktemp -d)
  mkdir -p "$d/bin"
  printf '#!/bin/sh\nexit 1\n' > "$d/bin/gh"; printf '#!/bin/sh\nexit 1\n' > "$d/bin/git"
  chmod +x "$d/bin/gh" "$d/bin/git"
  printf '%s\n' "$body" > "$d/issue.md"
  ( cd "$d" && env PATH="$d/bin:$PATH" ISSUE=1 "$@" bash "$STEPS/resolve-version.sh" 2>/dev/null )
}

field() { printf '%s\n' "$1" | grep -E "^$2=" | head -1 | cut -d= -f2-; }

out=$(run_resolve "Broken on Shopware 6.7.2.0 when applying a promotion")
assert_eq "$(field "$out" target_version)" "6.7.2.0" "exact 4-part version used verbatim"
assert_eq "$(field "$out" is_trunk)" "false" "exact version is not trunk"
assert_eq "$(field "$out" provision_version)" "v6.7.2.0" "provision_version is v-prefixed"
assert_eq "$(field "$out" composer_root_version)" ".auto" "6.7.x uses .auto composer root"

out=$(run_resolve "bumped symfony/messenger to 6.4.2 in my plugin, running Shopware 6.7.0.0")
assert_eq "$(field "$out" target_version)" "6.7.0.0" "Shopware-anchored version beats a stray 6.4.2"

out=$(run_resolve "The checkout page crashes when applying a promotion code.")
assert_eq "$(field "$out" target_version)" "trunk" "no version + no date + offline -> trunk"
assert_eq "$(field "$out" is_trunk)" "true" "version-less report falls back to trunk"

out=$(run_resolve "Regression in Shopware 6.6.10.0 order flow")
assert_eq "$(field "$out" composer_root_version)" "6.6.x-dev" "6.6.x pins the 6.6.x-dev composer root"
assert_eq "$(field "$out" legacy_conflicts_alias)" "true" "6.6.10.0 enables the legacy conflicts alias"

finish
