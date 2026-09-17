#!/usr/bin/env bash
# Put the `shot` CLI on the agent's PATH and hand the swap its coordinates.
#
# The shim goes to /usr/local/bin rather than GITHUB_PATH: GITHUB_PATH does not propagate into the
# agent's sandbox, but /usr/local/bin is on its PATH.
#
# No browser is downloaded here. The agent's playwright-cli drives the runner's preinstalled Chrome
# (Playwright's default `channel: chrome`), and nothing in the `shot` CLI opens a browser at all —
# seeding goes over the Admin API, diffing over pixelmatch.
#
# Env: PR_REPO (required), GITHUB_WORKSPACE (required), SHOT_BIN_DIR (optional, default /usr/local/bin).
# Reads: base-sha.txt / head-ref.txt from fetch-context.sh, when the run is a pull request.
set -euo pipefail

: "${PR_REPO:?PR_REPO is required}"
: "${GITHUB_WORKSPACE:?GITHUB_WORKSPACE is required}"

ACTION="${GITHUB_WORKSPACE}/.github/actions/sw-screenshot"
SHOT_BIN_DIR="${SHOT_BIN_DIR:-/usr/local/bin}"

( cd "$ACTION" && npm ci --no-audit --no-fund )

printf '#!/usr/bin/env bash\nexec node --experimental-strip-types %s/cli/shot.ts "$@"\n' "$ACTION" \
  | sudo tee "${SHOT_BIN_DIR}/shot" >/dev/null
sudo chmod +x "${SHOT_BIN_DIR}/shot"

{
  echo "BASE_SHA=$(cat base-sha.txt 2>/dev/null || echo trunk)"
  echo "HEAD_REF=$(cat head-ref.txt 2>/dev/null || echo '')"
  echo "PR_REPO=${PR_REPO}"
  echo "SW_SHOT_SWAP_DIR=${GITHUB_WORKSPACE}/.sw-screenshot-swap"
} >> "$GITHUB_ENV"

shot --help >/dev/null 2>&1 || shot >/dev/null 2>&1 || { echo "::error::the shot shim does not run"; exit 1; }
echo "shot CLI installed"
