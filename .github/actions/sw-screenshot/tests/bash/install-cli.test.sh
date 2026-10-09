#!/usr/bin/env bash
# The install step is the last thing between provisioning and the agent, so what it leaves behind is
# tested: a shim that runs, the swap's coordinates in GITHUB_ENV, and no browser download — the
# agent's playwright-cli uses the runner's Chrome, and a stray `playwright install` costs 300 MB.
set -euo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
source "$HERE/lib.sh"

STEP="$HERE/../../steps/install-cli.sh"
REPO_ROOT=$(cd "$HERE/../../../../.." && pwd)

work=$(mktemp -d)
mkdir -p "$work/bin" "$work/shim" "$work/cwd"

# `npm ci` is the real install, already done by whoever runs this suite; `sudo` has nothing to
# elevate when the shim goes to a scratch directory.
cat > "$work/bin/npm" <<'STUB'
#!/usr/bin/env bash
echo "npm $*" >> "${STUB_LOG}"
STUB
cat > "$work/bin/sudo" <<'STUB'
#!/usr/bin/env bash
exec "$@"
STUB
# Any attempt to download a browser has to show up as a failure, not as a slow success.
cat > "$work/bin/npx" <<'STUB'
#!/usr/bin/env bash
echo "npx $*" >> "${STUB_LOG}"
exit 1
STUB
chmod +x "$work/bin/"*

echo "install-cli.sh"

run() {
  : > "$work/stub.log"
  : > "$work/github.env"
  (
    cd "$work/cwd" \
    && STUB_LOG="$work/stub.log" \
       PATH="$work/bin:$work/shim:$PATH" \
       GITHUB_WORKSPACE="$REPO_ROOT" \
       GITHUB_ENV="$work/github.env" \
       SHOT_BIN_DIR="$work/shim" \
       PR_REPO="shopware/shopware" \
       bash "$STEP" 2>&1
  )
}

output=$(run) || { echo "  FAIL step exited non-zero"; echo "$output"; FAIL=$((FAIL + 1)); }
assert_contains "shot CLI installed" "$output" "completes"
assert_contains "npm ci" "$(cat "$work/stub.log")" "installs the toolchain"

if grep -q '^npx' "$work/stub.log"; then
  echo "  FAIL ran npx: $(grep '^npx' "$work/stub.log")"; FAIL=$((FAIL + 1))
else
  echo "  ok   downloads no browser"; PASS=$((PASS + 1))
fi

assert_contains "cli/shot.ts" "$(cat "$work/shim/shot")" "shim points at the CLI"
[ -x "$work/shim/shot" ] && { echo "  ok   shim is executable"; PASS=$((PASS + 1)); } \
  || { echo "  FAIL shim is not executable"; FAIL=$((FAIL + 1)); }

env_file=$(cat "$work/github.env")
assert_contains "BASE_SHA=trunk" "$env_file" "defaults the base to trunk without a pull request"
assert_contains "HEAD_REF=" "$env_file" "exports an empty head ref without a pull request"
assert_contains "PR_REPO=shopware/shopware" "$env_file" "passes the repository through"
assert_contains "SW_SHOT_SWAP_DIR=${REPO_ROOT}/.sw-screenshot-swap" "$env_file" "places the swap dir in the workspace"

# With fetch-context.sh's output present, the pull request's own refs win.
printf 'abc123' > "$work/cwd/base-sha.txt"
printf 'feature/x' > "$work/cwd/head-ref.txt"
run >/dev/null || true
env_file=$(cat "$work/github.env")
assert_contains "BASE_SHA=abc123" "$env_file" "uses the fetched base sha"
assert_contains "HEAD_REF=feature/x" "$env_file" "uses the fetched head ref"

rm -rf "$work"
finish
