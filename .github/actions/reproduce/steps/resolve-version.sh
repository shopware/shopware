#!/usr/bin/env bash
# The one thing that must be known before the agent runs: the reported Shopware VERSION (it decides
# the provision target). Everything else the agent records in reproduction-plan.json.
#
# Matcher (first match wins; `v` optional; 2–4 numeric segments; `.*`/`.x` wildcard tail):
#   6.7.2.0            exact 4-part           -> 6.7.2.0            (used as-is)
#   6.7.10 / 6.7.10.*  3-part / patch wildcard-> LATEST v6.7.10.* as of the issue's open date
#   6.7 / 6.7.*        minor only / wildcard  -> LATEST v6.7.* as of the issue's open date
#   (none)                                    -> LATEST release as of the issue's open date (else trunk)
# Underspecified versions resolve to the newest existing tag that was released ON/BEFORE the issue
# was opened (what the reporter most likely ran — the bug lives there, before any later fix), never
# `.0`. A version-less report resolves the same way against ALL releases (a reporter runs a release,
# not trunk). If the tag list is unreachable (offline) or the date is unknown, degrade to trunk.
#
# Env: ISSUE (req); REPO (fallback fetch); UPSTREAM (tag repo, default shopware/shopware); GH_TOKEN.
# Emits target_version, is_trunk, provision_version, composer_root_version, legacy_conflicts_alias.
set -euo pipefail

: "${ISSUE:?ISSUE is required}"
UPSTREAM=${UPSTREAM:-shopware/shopware}
out () { [ -n "${GITHUB_OUTPUT:-}" ] && echo "$1=$2" >> "$GITHUB_OUTPUT"; echo "$1=$2"; }

# Newest existing upstream tag under a (partial) base. When the issue's open date is known, cap at
# the newest tag released ON/BEFORE it: the reporter ran whatever was latest when they filed, which
# is where the bug lives (before any fix released later). Trailing dot keeps "6.7" off "6.70".
resolve_latest () {
  # Stable tags only — keep purely numeric X.Y.Z.W, dropping any -rc/-dev/-beta: a reporter runs a
  # release, not a prerelease, and a prerelease's version sorts ABOVE its own final while carrying an
  # older date, which would let the date walk below pick an RC over the stable release it precedes.
  # Empty base = "any version" (used for a version-less report): match all v-tags, no trailing dot.
  # NB: assign `base` in its own `local` first. A single `local base="$1" ghpat="v${base}."` expands
  # ${base} before the assignment completes, which under `set -u` throws "base: unbound variable" —
  # so every resolve_latest call (any underspecified/version-less issue) would have errored.
  local base="${1:-}" tags=""
  local ghpat="v${base}." glob="v${base}.*"
  [ -z "$base" ] && { ghpat="v"; glob="v*"; }
  if command -v gh >/dev/null 2>&1; then
    tags=$(gh api "repos/${UPSTREAM}/git/matching-refs/tags/${ghpat}" --jq '.[].ref' 2>/dev/null | sed 's#refs/tags/##; s/^v//' | grep -E '^[0-9]+(\.[0-9]+)+$' | sort -Vr || true)
  fi
  if [ -z "$tags" ] && command -v git >/dev/null 2>&1; then
    tags=$(git ls-remote --tags --refs "https://github.com/${UPSTREAM}.git" "$glob" 2>/dev/null | sed 's#.*refs/tags/##; s/^v//' | grep -E '^[0-9]+(\.[0-9]+)+$' | sort -Vr || true)
  fi
  [ -n "$tags" ] || { printf ''; return; }
  if [ -z "${ISSUE_EPOCH:-}" ]; then printf '%s\n' "$tags" | head -1; return; fi   # no date → newest
  local t d de saw_date=0
  while IFS= read -r t; do
    [ -n "$t" ] || continue
    d=$(gh api "repos/${UPSTREAM}/commits/v${t}" --jq '.commit.committer.date' 2>/dev/null || echo "")
    [ -n "$d" ] || continue
    de=$(date -u -d "$d" +%s 2>/dev/null || echo 0)
    [ "$de" -gt 0 ] || continue
    saw_date=1   # at least one tag date resolved → the date walk is meaningful
    [ "$de" -le "$ISSUE_EPOCH" ] && { printf '%s' "$t"; return; }
  done <<< "$tags"
  # Two ways to fall through the walk, and they must not be conflated: (a) we resolved dates but none
  # were on/before the issue → the issue genuinely predates every tag in this base, so the earliest
  # available tag is the best answer; (b) NOT ONE commit date resolved (rate-limit / offline) → we
  # know nothing, so return empty and let the caller degrade to trunk rather than silently
  # provisioning the oldest release ever built.
  [ "$saw_date" = 1 ] || { printf ''; return; }
  printf '%s\n' "$tags" | tail -1   # issue predates every tag in this base → earliest available
}

BODY=""
if [ -f issue.md ]; then BODY=$(cat issue.md)
elif command -v gh >/dev/null 2>&1; then BODY=$(gh issue view "$ISSUE" --repo "${REPO:-${GITHUB_REPOSITORY:-}}" --json title,body --jq '.title + "\n" + (.body // "")' 2>/dev/null || echo ""); fi

# Open date of the issue → epoch. Underspecified versions resolve to the newest tag as of this date
# (see resolve_latest). ISSUE_CREATED_AT overrides for testing; otherwise read it from the issue.
ISSUE_DATE="${ISSUE_CREATED_AT:-}"
if [ -z "$ISSUE_DATE" ] && command -v gh >/dev/null 2>&1; then
  ISSUE_DATE=$(gh issue view "$ISSUE" --repo "${REPO:-${GITHUB_REPOSITORY:-}}" --json createdAt --jq '.createdAt' 2>/dev/null || echo "")
fi
ISSUE_EPOCH=""; [ -n "$ISSUE_DATE" ] && ISSUE_EPOCH=$(date -u -d "$ISSUE_DATE" +%s 2>/dev/null || echo "")

# Boundary before the version so "16.7"/"8.6" can't false-match; ERE only (portable).
VER_RE='v?6(\.[0-9]+){1,3}(\.(\*|[xX]))?'
# Anchor to Shopware context first: a version preceded by "Shopware" (optionally "v"/"version") on the
# same line wins, so "symfony/messenger to 6.4.2 … Shopware 6.7.0.0" resolves the Shopware 6.7.0.0 and
# not the first stray 6.x. Only if no Shopware-tied version is found do we fall back to the broad match.
RAW=$(printf '%s' "$BODY" | grep -oiE "shopware[^0-9]{0,20}${VER_RE}" | head -1 || true)
if [ -z "$RAW" ]; then
  RAW=$(printf '%s' "$BODY" | grep -oiE "(^|[^0-9.])${VER_RE}" | head -1 || true)
fi
RAW=$(printf '%s' "$RAW"  | grep -oiE "$VER_RE" | head -1 || true)

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
elif [ -n "${ISSUE_EPOCH:-}" ]; then
  # No version cited, but we know when it was filed: a version-less reporter almost certainly ran a
  # release, not trunk, so provision the newest release that existed then rather than today's trunk.
  VERSION=$(resolve_latest "")
  [ -n "$VERSION" ] && IS_TRUNK=false || echo "== no version cited and no dated release resolvable — using trunk =="
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
echo "== resolve-version: matched='${RAW:-<none>}' -> '${VERSION:-trunk}' (is_trunk=$IS_TRUNK, as-of='${ISSUE_DATE:-<unknown>}') =="
