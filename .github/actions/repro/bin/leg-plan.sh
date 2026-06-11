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
out admin_build "$(jq -r .build_profile.admin_build "$ANALYSIS")"
out storefront_build "$(jq -r .build_profile.storefront_build "$ANALYSIS")"
