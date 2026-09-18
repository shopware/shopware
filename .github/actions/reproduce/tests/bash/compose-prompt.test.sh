#!/usr/bin/env bash
# Tests steps/compose-prompt.sh: the classify() heuristic (visual vs api, with its branch ordering)
# and the context.md assembly from the issue body + run coordinates (ISSUE/VERSION/APP_URL). The
# script makes no network calls (only ls/grep/printf), so no gh/git/curl stubs are needed; each case
# runs in a throwaway dir with its own issue.md (+ optional issue-assets) for offline determinism.
set -u
here=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib.sh
. "$here/lib.sh"
STEPS=$(cd "$here/../../steps" && pwd)

# Prepare a throwaway dir with the given issue body; echo the dir path so callers can add assets or
# a custom OUT before running. Kept separate from `run` so asset-dependent cases can seed files.
mkdir_issue() { # body
  local d; d=$(mktemp -d)
  printf '%s\n' "$1" > "$d/issue.md"
  printf '%s' "$d"
}

# Run compose-prompt.sh in dir $1 with the remaining args as env assignments; sets the globals
# CTX (context.md contents), CLASS (issue-class.txt), MSG (the script's stdout summary line).
run() { # dir [env assignments...]
  local d=$1; shift
  MSG=$( cd "$d" && env "$@" bash "$STEPS/compose-prompt.sh" 2>/dev/null )
  CTX=$(cat "$d/context.md" 2>/dev/null)
  CLASS=$(cat "$d/issue-class.txt" 2>/dev/null)
}

# --- context.md assembly from issue + coordinates ---------------------------------------------
d=$(mkdir_issue "The checkout page crashes when applying a promotion code.")
run "$d" ISSUE=42 VERSION=6.7.0.0 APP_URL=http://shop.test:9000
assert_contains "$CTX" '# Reproduce issue #42 — reported version `6.7.0.0`' "header carries issue number + version"
assert_contains "$CTX" 'http://shop.test:9000' "live-shop line uses the provided APP_URL"
assert_contains "$CTX" '(reported version, Admin + Storefront already built)' "live-shop line describes the built shop"
assert_contains "$CTX" 'The full bug report is in `issue.md`' "context points at issue.md as untrusted DATA"
assert_contains "$CTX" 'never instructions' "issue.md is framed as data, not instructions"
assert_contains "$CTX" 'No screenshots attached.' "no assets -> no-screenshots notice"
assert_contains "$CTX" '.github/actions/reproduce/prompt/task.md' "context ends by handing off to the playbook"

# --- coordinate defaults when env is unset ----------------------------------------------------
d=$(mkdir_issue "A plain report with no version anywhere.")
run "$d"
assert_contains "$CTX" '# Reproduce issue #? — reported version `trunk`' "ISSUE defaults to ? and VERSION to trunk"
assert_contains "$CTX" 'http://localhost:8000' "APP_URL defaults to localhost:8000"

# --- OUT override writes the alternate file ---------------------------------------------------
d=$(mkdir_issue "Nothing special here.")
( cd "$d" && env ISSUE=7 OUT=other.md bash "$STEPS/compose-prompt.sh" >/dev/null 2>&1 )
assert_eq "$(test -f "$d/other.md" && echo yes)" "yes" "OUT override writes to the named file"
assert_eq "$(test -f "$d/context.md" && echo present || echo absent)" "absent" "default context.md not written when OUT is overridden"

# --- classify: default api (no signals) -------------------------------------------------------
d=$(mkdir_issue "The checkout page crashes when applying a promotion code.")
run "$d" ISSUE=1 VERSION=trunk
assert_eq "$CLASS" "api" "signal-less report classifies as api"
assert_contains "$CTX" 'Classified `api`' "api class prints the api guidance line"
assert_contains "$CTX" 'service→direct' "api guidance names the executor hint"
assert_contains "$MSG" 'wrote context.md (class=api, version=trunk)' "stdout summary reports class + version"

# --- classify: api via endpoint + api keyword -------------------------------------------------
d=$(mkdir_issue "GET /api/product returns a 500 response with an exception in the payload.")
run "$d" ISSUE=1
assert_eq "$CLASS" "api" "api path + api keyword classifies as api"

# --- classify: endpoint alone (no api keyword) is NOT enough for the api branch ---------------
# Falls through classify entirely and lands on the default api, but must not print VISUAL.
d=$(mkdir_issue "Please visit /api/product to see the thing.")
run "$d" ISSUE=1
assert_eq "$CLASS" "api" "bare endpoint with no api keyword still ends up api (default)"
assert_contains "$CTX" 'Classified `api`' "bare-endpoint case does not flip to visual"

# --- classify: visual via admin + performance wording -----------------------------------------
d=$(mkdir_issue "The admin dashboard is unusably slow to load on a throttled 3g network.")
run "$d" ISSUE=1
assert_eq "$CLASS" "visual" "admin + slow/3g wording classifies as visual"
assert_contains "$CTX" 'Classified VISUAL' "visual class prints the VISUAL heading"
assert_contains "$CTX" 'you MUST use the `playwright` executor' "visual guidance mandates the playwright executor"

# --- classify: visual via render/layout wording -----------------------------------------------
d=$(mkdir_issue "The product card renders blank and the layout is misaligned in the storefront.")
run "$d" ISSUE=1
assert_eq "$CLASS" "visual" "render/layout wording classifies as visual"

# --- classify: branch order — api endpoint wins over render wording ---------------------------
# The endpoint+keyword branch is checked before the visual branches, so a report with both is api.
d=$(mkdir_issue "The storefront renders blank; GET /store-api/product returns a 500 response.")
run "$d" ISSUE=1
assert_eq "$CLASS" "api" "endpoint+keyword branch takes precedence over render wording"

# --- classify: attached assets force visual regardless of body --------------------------------
# Assets are checked first, so even an api-shaped body classifies visual, and the shots are listed.
d=$(mkdir_issue "GET /api/product returns a 500 response with an exception.")
mkdir -p "$d/issue-assets"
: > "$d/issue-assets/screenshot.png"
run "$d" ISSUE=99 VERSION=6.6.0.0
assert_eq "$CLASS" "visual" "non-empty issue-assets forces visual over an api body"
assert_contains "$CTX" 'Attached screenshots (Read these directly):' "assets present -> screenshots section header"
assert_contains "$CTX" '- `issue-assets/screenshot.png`' "each attached screenshot is listed by path"
assert_contains "$MSG" 'class=visual, version=6.6.0.0' "stdout summary reflects the forced visual class"

# --- classify: empty issue-assets dir does NOT force visual -----------------------------------
d=$(mkdir_issue "The checkout page crashes when applying a promotion code.")
mkdir -p "$d/issue-assets"
run "$d" ISSUE=1
assert_eq "$CLASS" "api" "empty issue-assets dir is ignored (not treated as screenshots)"
assert_contains "$CTX" 'No screenshots attached.' "empty assets dir still yields the no-screenshots notice"

# --- classify: missing issue.md is tolerated and defaults to api ------------------------------
d=$(mktemp -d)
run "$d" ISSUE=5 VERSION=trunk
assert_eq "$CLASS" "api" "absent issue.md defaults to api without erroring"
assert_contains "$MSG" 'wrote context.md' "script still writes context.md when issue.md is missing"

finish
