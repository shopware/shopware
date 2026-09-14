#!/usr/bin/env bash
# Put the `shot` CLI on the agent's PATH and hand the swap its coordinates.
#
# The shim goes to /usr/local/bin rather than GITHUB_PATH: GITHUB_PATH does not propagate into the
# agent's sandbox, but /usr/local/bin is on its PATH.
#
# Env: PR_REPO (required), GITHUB_WORKSPACE (required).
# Reads: base-sha.txt / head-ref.txt from fetch-context.sh, when the run is a pull request.
set -euo pipefail

: "${PR_REPO:?PR_REPO is required}"
: "${GITHUB_WORKSPACE:?GITHUB_WORKSPACE is required}"

ACTION="${GITHUB_WORKSPACE}/.github/actions/sw-screenshot"

( cd "$ACTION" && npm ci --no-audit --no-fund )
npx --yes playwright install --with-deps chromium

printf '#!/usr/bin/env bash\nexec node --experimental-strip-types %s/cli/shot.ts "$@"\n' "$ACTION" \
  | sudo tee /usr/local/bin/shot >/dev/null
sudo chmod +x /usr/local/bin/shot

{
  echo "BASE_SHA=$(cat base-sha.txt 2>/dev/null || echo trunk)"
  echo "HEAD_REF=$(cat head-ref.txt 2>/dev/null || echo '')"
  echo "PR_REPO=${PR_REPO}"
  echo "SW_SHOT_SWAP_DIR=${GITHUB_WORKSPACE}/.sw-screenshot-swap"
} >> "$GITHUB_ENV"

shot --help >/dev/null 2>&1 || shot >/dev/null 2>&1 || { echo "::error::the shot shim does not run"; exit 1; }
echo "shot CLI installed"
