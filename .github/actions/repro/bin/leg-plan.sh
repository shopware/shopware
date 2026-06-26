#!/usr/bin/env bash
# Derive one reproduce leg's parameters from the plan + the leg's matrix version.
#
# Env: LEG_VERSION (req, the matrix entry), BRANCH (default trunk),
#      ANALYSIS (default analysis.json).
# Emits role|version|executor|admin_build|storefront_build to $GITHUB_OUTPUT (and stdout).
set -euo pipefail

: "${LEG_VERSION:?LEG_VERSION is required}"
BRANCH=${BRANCH:-trunk}
ANALYSIS=${ANALYSIS:-analysis.json}

out () { [ -n "${GITHUB_OUTPUT:-}" ] && echo "$1=$2" >> "$GITHUB_OUTPUT"; echo "$1=$2"; }

# role derived: the leg whose version is the branch is "trunk", else "reported".
if [ "$LEG_VERSION" = "$BRANCH" ]; then ROLE=trunk; else ROLE=reported; fi
out role "$ROLE"
out executor "$(jq -r .executor "$ANALYSIS")"
# trunk leg provisions the branch ref as-is (default trunk); reported leg pins the
# exact released tag (v-prefixed).
if [ "$ROLE" = "trunk" ]; then out version "$LEG_VERSION"; else out version "v$LEG_VERSION"; fi

# Build profile is DERIVABLE from the layer — don't trust the agent to set it. An admin-ui
# repro drives the admin SPA, which only exists once its JS bundle is compiled: with
# admin_build=false the /admin route serves a dead shell, the login form never renders, and
# EVERY admin-ui leg dies "harness admin login failed" (hit live on #29, whose plan set
# admin_build:false). A client-side storefront-ui (playwright) repro likewise needs the
# storefront JS built (a `direct` storefront render test does NOT — it has no browser). Enforce
# the layer→profile contract here so a forgetful plan still runs instead of silently blocking.
LAYER=$(jq -r '.layer // ""' "$ANALYSIS")
EXECUTOR=$(jq -r '.executor // ""' "$ANALYSIS")
ADMIN_BUILD=$(jq -r '.build_profile.admin_build' "$ANALYSIS")
STOREFRONT_BUILD=$(jq -r '.build_profile.storefront_build' "$ANALYSIS")
if [ "$LAYER" = "admin-ui" ]; then ADMIN_BUILD=true; fi
if [ "$LAYER" = "storefront-ui" ] && [ "$EXECUTOR" = "playwright" ]; then STOREFRONT_BUILD=true; fi
out admin_build "$ADMIN_BUILD"
out storefront_build "$STOREFRONT_BUILD"
