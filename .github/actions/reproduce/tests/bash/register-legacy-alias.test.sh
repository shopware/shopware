#!/usr/bin/env bash
# Tests steps/register-legacy-alias.sh: the offline step that registers a shopware/conflicts
# metapackage alias so the 6.6.10.0 quirk can be provisioned by Composer. Two angles:
#   1. Direct behaviour of the script (config.json content, COMPOSER_HOME export, stdout).
#   2. The "alias emitted only for the flagged version" property, which the workflow enforces via
#      `if: steps.version.outputs.legacy_conflicts_alias == 'true'`. We reproduce that gate by
#      running resolve-version.sh first and only invoking the alias step when it reports true.
# gh/git are stubbed to fail so resolve-version stays offline and deterministic.
set -u
here=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib.sh
. "$here/lib.sh"
STEPS=$(cd "$here/../../steps" && pwd)

# Run register-legacy-alias.sh in a throwaway workspace. Sets RUNNER_TEMP + GITHUB_ENV to files
# under that workspace so nothing escapes the temp dir. Echoes the script's stdout; the resulting
# files live under the returned workspace dir (printed on the first line as WORKDIR=...).
run_register() { # -> prints "WORKDIR=<dir>" then the script stdout
  local d; d=$(mktemp -d)
  local out
  out=$(env RUNNER_TEMP="$d" GITHUB_ENV="$d/github_env" bash "$STEPS/register-legacy-alias.sh" 2>&1)
  local rc=$?
  printf 'WORKDIR=%s\n' "$d"
  printf 'RC=%s\n' "$rc"
  printf '%s\n' "$out"
}

# --- 1. Direct behaviour ------------------------------------------------------------------------

res=$(run_register)
workdir=$(printf '%s\n' "$res" | grep -E '^WORKDIR=' | head -1 | cut -d= -f2-)
rc=$(printf '%s\n' "$res" | grep -E '^RC=' | head -1 | cut -d= -f2-)
stdout=$(printf '%s\n' "$res" | grep -vE '^WORKDIR=|^RC=')

assert_eq "$rc" "0" "script exits 0"

cfg="$workdir/composer-home-legacy-shopware/config.json"
[ -f "$cfg" ] && ok=yes || ok=no
assert_eq "$ok" "yes" "config.json written under RUNNER_TEMP/composer-home-legacy-shopware"

cfg_body=$(cat "$cfg" 2>/dev/null)
assert_contains "$cfg_body" '"shopware/conflicts"' "alias names the shopware/conflicts package"
assert_contains "$cfg_body" '"6.6.x-dev"'          "alias version is 6.6.x-dev"
assert_contains "$cfg_body" '"metapackage"'        "alias is a metapackage"
assert_contains "$cfg_body" '"canonical": false'   "repository is non-canonical (overlay only)"
assert_contains "$cfg_body" '"symfony/symfony": "*"' "conflict constraints carried through"

# COMPOSER_HOME is exported to $GITHUB_ENV and points at the created composer home.
env_body=$(cat "$workdir/github_env" 2>/dev/null)
assert_eq "$env_body" "COMPOSER_HOME=$workdir/composer-home-legacy-shopware" \
  "COMPOSER_HOME appended to GITHUB_ENV pointing at the alias home"

assert_contains "$stdout" "registered shopware/conflicts 6.6.x-dev metapackage alias" \
  "prints the confirmation line"

# config.json must be valid JSON (node is available in this action dir). Guarded so the test still
# runs where node is absent.
if command -v node >/dev/null 2>&1; then
  node -e "JSON.parse(require('fs').readFileSync(process.argv[1],'utf8'))" "$cfg" >/dev/null 2>&1 \
    && json_ok=yes || json_ok=no
  assert_eq "$json_ok" "yes" "config.json parses as valid JSON"
fi

# --- 2. Gate: alias emitted only for the flagged version --------------------------------------
# Reproduce the workflow gate. resolve-version.sh decides legacy_conflicts_alias; the alias step
# runs only when it is true. We assert the alias config exists exactly for 6.6.10.0.

gate() { # issue-body -> "flag|<config-exists yes/no>"
  local body=$1
  local d; d=$(mktemp -d)
  mkdir -p "$d/bin"
  printf '#!/bin/sh\nexit 1\n' > "$d/bin/gh"; printf '#!/bin/sh\nexit 1\n' > "$d/bin/git"
  chmod +x "$d/bin/gh" "$d/bin/git"
  printf '%s\n' "$body" > "$d/issue.md"
  # Resolve version offline (gh/git stubbed to fail -> no network lookups).
  local rv flag
  rv=$(cd "$d" && env PATH="$d/bin:$PATH" ISSUE=1 bash "$STEPS/resolve-version.sh" 2>/dev/null)
  flag=$(printf '%s\n' "$rv" | grep -E '^legacy_conflicts_alias=' | head -1 | cut -d= -f2-)
  # Workflow gate: only register when the flag is exactly "true".
  if [ "$flag" = "true" ]; then
    env RUNNER_TEMP="$d" GITHUB_ENV="$d/github_env" bash "$STEPS/register-legacy-alias.sh" >/dev/null 2>&1
  fi
  local exists=no
  [ -f "$d/composer-home-legacy-shopware/config.json" ] && exists=yes
  printf '%s|%s\n' "$flag" "$exists"
}

g=$(gate "Regression in Shopware 6.6.10.0 order flow")
assert_eq "$g" "true|yes"   "6.6.10.0 flags the alias AND the config gets written"

g=$(gate "Broken on Shopware 6.7.2.0 when applying a promotion")
assert_eq "$g" "false|no"   "6.7.2.0 does not flag the alias -> no config written"

g=$(gate "Order flow bug in Shopware 6.6.9.0")
assert_eq "$g" "false|no"   "a different 6.6.x patch (6.6.9.0) does not flag the alias"

g=$(gate "The checkout page crashes when applying a promotion code.")
assert_eq "$g" "false|no"   "version-less report falls back to trunk -> no alias"

finish
