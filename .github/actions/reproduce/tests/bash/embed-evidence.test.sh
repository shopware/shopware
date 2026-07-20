#!/usr/bin/env bash
# Tests the offline paths of report/embed-evidence.sh: the required-env guards, the hard branch guard
# (must be ci/repro-evidence), which artifact legs get gathered (playwright + screenshot only), the
# evidence.json manifest and its run-id-scoped raw URLs, and the push/no-push behaviour. git is stubbed
# via a temp PATH and logs every call, so we can assert that PUSH=skip / the empty-evidence early exit
# never touch git, while the real push path does. No network, jq is the only external dependency.
set -u
here=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib.sh
. "$here/lib.sh"
REPORT=$(cd "$here/../../report" && pwd)
SCRIPT="$REPORT/embed-evidence.sh"

# Fresh workdir with a git stub on PATH that appends its args to $d/git-log (so a call is observable)
# and always succeeds. Everything the script needs lives under $d so runs are isolated.
new_dir() {
  local d; d=$(mktemp -d)
  mkdir -p "$d/bin" "$d/artifacts"
  printf '#!/bin/sh\necho "$*" >> "%s/git-log"\nexit 0\n' "$d" > "$d/bin/git"
  chmod +x "$d/bin/git"
  printf '%s' "$d"
}

# Create an artifacts/repro-<name> leg. png/webm args are literally "png"/"webm" to include the file.
mk_leg() { # dir name executor status png webm
  local d=$1 name=$2 executor=$3 status=$4 png=$5 webm=$6
  local dir="$d/artifacts/repro-$name"
  mkdir -p "$dir"
  printf '{"executor":"%s","status":"%s"}\n' "$executor" "$status" > "$dir/result.json"
  [ "$png" = png ] && printf 'PNG' > "$dir/test-shot.png"
  [ "$webm" = webm ] && printf 'WEBM' > "$dir/clip.webm"
  return 0
}

# Run the script in $d with the given env assignments; capture combined output in OUT_TEXT and RC.
# Every variable the script reads is cleared first (-u ...) so nothing leaks from the ambient env
# (e.g. a real TOKEN in CI) and each case is driven purely by the assignments the test passes.
run_embed() { # dir [ENV=val ...]
  local d=$1; shift
  RC=0
  OUT_TEXT=$( cd "$d" && env -u TOKEN -u PUSH -u ART -u OUT -u BRANCH -u REPO -u RUN_ID \
    PATH="$d/bin:$PATH" "$@" bash "$SCRIPT" 2>&1 ) || RC=$?
}

manifest() { jq -r "$2" "$1/evidence.json"; }

# --- required-env guards -------------------------------------------------------------------------
d=$(new_dir)
run_embed "$d" BRANCH=ci/repro-evidence REPO=acme/shop
assert_eq "$RC" "1" "missing RUN_ID fails"
assert_contains "$OUT_TEXT" "RUN_ID" "missing RUN_ID names the variable"

d=$(new_dir)
run_embed "$d" REPO=acme/shop RUN_ID=1
assert_eq "$RC" "1" "missing BRANCH fails"
assert_contains "$OUT_TEXT" "BRANCH" "missing BRANCH names the variable"

# --- branch guard --------------------------------------------------------------------------------
d=$(new_dir)
run_embed "$d" BRANCH=trunk REPO=acme/shop RUN_ID=1
assert_eq "$RC" "1" "non-evidence branch is refused"
assert_contains "$OUT_TEXT" "ci/repro-evidence" "guard names the required branch"
assert_contains "$OUT_TEXT" "trunk" "guard echoes the rejected branch"
assert_eq "$( [ -f "$d/git-log" ] && echo yes || echo no )" "no" "guard fails before any git call"

# --- no playwright evidence: early exit, empty manifest, no push ---------------------------------
d=$(new_dir)
mk_leg "$d" cart http passed png webm            # non-playwright: skipped
mk_leg "$d" login playwright passed nopng nowebm # playwright without screenshot: skipped
run_embed "$d" BRANCH=ci/repro-evidence REPO=acme/shop RUN_ID=42 PUSH=skip
assert_eq "$RC" "0" "no evidence exits 0"
assert_contains "$OUT_TEXT" "no playwright evidence" "reports the empty case"
assert_eq "$(manifest "$d" '.legs | length')" "0" "manifest has zero legs"
assert_eq "$( [ -f "$d/git-log" ] && echo yes || echo no )" "no" "nothing to publish means no push"

# --- happy path: manifest, run-id-scoped URLs, PUSH=skip does not push ---------------------------
d=$(new_dir)
mk_leg "$d" checkout playwright passed png webm
run_embed "$d" BRANCH=ci/repro-evidence REPO=acme/shop RUN_ID=987654 PUSH=skip
assert_eq "$RC" "0" "happy path exits 0"
assert_contains "$OUT_TEXT" "published evidence for 1 leg(s)" "reports one published leg"
assert_eq "$(manifest "$d" '.legs | length')" "1" "one leg in the manifest"
assert_eq "$(manifest "$d" '.legs[0].name')" "checkout" "leg name strips the repro- prefix"
assert_eq "$(manifest "$d" '.legs[0].status')" "passed" "leg status carried from result.json"
assert_eq "$(manifest "$d" '.legs[0].png')" \
  "https://raw.githubusercontent.com/acme/shop/ci/repro-evidence/runs/987654/checkout.png" \
  "png URL is scoped to runs/<RUN_ID>"
assert_eq "$(manifest "$d" '.legs[0].webm')" \
  "https://raw.githubusercontent.com/acme/shop/ci/repro-evidence/runs/987654/checkout.webm" \
  "webm URL is scoped to runs/<RUN_ID>"
assert_eq "$( [ -f "$d/git-log" ] && echo yes || echo no )" "no" "PUSH=skip performs no git push"

# --- webm is optional: a screenshot-only leg yields an empty webm field --------------------------
d=$(new_dir)
mk_leg "$d" api playwright failed png nowebm
run_embed "$d" BRANCH=ci/repro-evidence REPO=acme/shop RUN_ID=7 PUSH=skip
assert_eq "$RC" "0" "screenshot-only leg exits 0"
assert_eq "$(manifest "$d" '.legs[0].webm')" "" "missing recording leaves webm empty"
assert_eq "$(manifest "$d" '.legs[0].png')" \
  "https://raw.githubusercontent.com/acme/shop/ci/repro-evidence/runs/7/api.png" \
  "screenshot URL still emitted"

# --- push path requires a TOKEN ------------------------------------------------------------------
# The token guard sits before any git call, so a missing token must abort with its error and never
# push. (The exit code itself is not asserted: the script's EXIT-trap cleanup resets $? to 0 on
# bash 3.2, so the code is platform-dependent — the durable contract is "error out, don't push".)
d=$(new_dir)
mk_leg "$d" checkout playwright passed png webm
run_embed "$d" BRANCH=ci/repro-evidence REPO=acme/shop RUN_ID=3
assert_contains "$OUT_TEXT" "TOKEN is required" "missing TOKEN reports the required variable"
assert_eq "$( [ -f "$d/git-log" ] && echo yes || echo no )" "no" "no push is attempted without a token"
case "$OUT_TEXT" in *"published evidence"*) msg=leaked ;; *) msg=ok ;; esac
assert_eq "$msg" "ok" "no success line emitted without a token"

# --- push path with a token actually pushes to the evidence branch -------------------------------
d=$(new_dir)
mk_leg "$d" checkout playwright passed png webm
run_embed "$d" BRANCH=ci/repro-evidence REPO=acme/shop RUN_ID=555 TOKEN=secret
assert_eq "$RC" "0" "push path with a token succeeds"
assert_eq "$( [ -f "$d/git-log" ] && echo yes || echo no )" "yes" "git is invoked when pushing"
assert_contains "$(cat "$d/git-log")" "push" "a push is issued to the remote"
assert_contains "$(cat "$d/git-log")" "refs/heads/ci/repro-evidence" "push targets the evidence branch"
assert_eq "$(manifest "$d" '.legs | length')" "1" "manifest still written on the push path"

finish
