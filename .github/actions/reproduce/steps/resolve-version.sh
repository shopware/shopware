#!/usr/bin/env bash
# The one thing that must be known before the agent runs: the reported Shopware VERSION (it decides
# the provision target). Everything else the agent records in reproduction-plan.json.
#
# Matcher (first match wins; `v` optional; 2–4 numeric segments; `.*`/`.x` wildcard tail):
#   6.7.2.0            exact 4-part           -> 6.7.2.0            (used as-is)
#   6.7.10 / 6.7.10.*  3-part / patch wildcard-> LATEST v6.7.10.*
#   6.7 / 6.7.*        minor only / wildcard  -> LATEST v6.7.*
#   (none)                                    -> trunk
# Underspecified versions resolve to the newest existing tag, never `.0`. If the tag list is
# unreachable (offline), degrade to trunk rather than guessing a patch.
#
# Env: ISSUE (req); REPO (fallback fetch); UPSTREAM (tag repo, default shopware/shopware); GH_TOKEN.
# Emits target_version, is_trunk, provision_version, composer_root_version, legacy_conflicts_alias.
set -euo pipefail

: "${ISSUE:?ISSUE is required}"
UPSTREAM=${UPSTREAM:-shopware/shopware}
out () { [ -n "${GITHUB_OUTPUT:-}" ] && echo "$1=$2" >> "$GITHUB_OUTPUT"; echo "$1=$2"; }

# Newest existing upstream tag under a (partial) base. Trailing dot keeps "6.7" off "6.70".
resolve_latest () {
  local base="$1" latest=""
  if command -v gh >/dev/null 2>&1; then
    latest=$(gh api "repos/${UPSTREAM}/git/matching-refs/tags/v${base}." --jq '.[].ref' 2>/dev/null | sed 's#refs/tags/##; s/^v//' | sort -V | tail -1 || true)
  fi
  if [ -z "$latest" ] && command -v git >/dev/null 2>&1; then
    latest=$(git ls-remote --tags --refs "https://github.com/${UPSTREAM}.git" "v${base}.*" 2>/dev/null | sed 's#.*refs/tags/##; s/^v//' | sort -V | tail -1 || true)
  fi
  printf '%s' "$latest"
}

BODY=""
if [ -f issue.md ]; then BODY=$(cat issue.md)
elif command -v gh >/dev/null 2>&1; then BODY=$(gh issue view "$ISSUE" --repo "${REPO:-${GITHUB_REPOSITORY:-}}" --json title,body --jq '.title + "\n" + (.body // "")' 2>/dev/null || echo ""); fi

# Boundary before the version so "16.7"/"8.6" can't false-match; ERE only (portable).
RAW=$(printf '%s' "$BODY" | grep -oiE '(^|[^0-9.])v?6(\.[0-9]+){1,3}(\.(\*|[xX]))?' | head -1 || true)
RAW=$(printf '%s' "$RAW"  | grep -oiE 'v?6(\.[0-9]+){1,3}(\.(\*|[xX]))?' || true)

VERSION=""; IS_TRUNK=true
if [ -n "$RAW" ]; then
  IS_TRUNK=false
  WILDCARD=0; case "$RAW" in *'.*'|*.[xX]) WILDCARD=1 ;; esac
  BASE=$(printf '%s' "$RAW" | sed -E 's/^[vV]//; s/\.(\*|[xX])$//')
  if [ "$WILDCARD" = 0 ] && [ "$(printf '%s' "$BASE" | awk -F. '{print NF}')" -eq 4 ]; then
    VERSION="$BASE"                       # a specific patch was reported — use it verbatim
  else
    VERSION=$(resolve_latest "$BASE")     # underspecified — newest patch, never .0
    if [ -z "$VERSION" ]; then echo "::warning::could not resolve '${RAW}' against ${UPSTREAM} (offline?) — falling back to trunk"; IS_TRUNK=true; fi
  fi
fi

out target_version "${VERSION:-trunk}"
out is_trunk "$IS_TRUNK"
if [ "$IS_TRUNK" = true ]; then
  out provision_version trunk
  out composer_root_version .auto
  out legacy_conflicts_alias false
else
  out provision_version "v$VERSION"
  case "$VERSION" in 6.6.*) out composer_root_version 6.6.x-dev ;; *) out composer_root_version .auto ;; esac
  [ "$VERSION" = "6.6.10.0" ] && out legacy_conflicts_alias true || out legacy_conflicts_alias false
fi
echo "== resolve-version: matched='${RAW:-<none>}' -> '${VERSION:-trunk}' (is_trunk=$IS_TRUNK) =="
