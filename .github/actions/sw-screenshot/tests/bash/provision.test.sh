#!/usr/bin/env bash
# provision.sh runs against a stubbed toolchain from an unrelated cwd. Everything it promises the
# workflow — the relocation, the forwarder, the exported URL — is checked here rather than trusted.
set -euo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
source "$HERE/lib.sh"

STEP="$HERE/../../steps/provision.sh"

work=$(mktemp -d)
mkdir -p "$work/bin" "$work/workspace/shop/bin" "$work/runner" "$work/elsewhere"

# Stand-ins for the shop toolchain. Each records its call so the assertions can check what ran.
for tool in php symfony curl composer socat sudo; do
  cat > "$work/bin/$tool" <<STUB
#!/usr/bin/env bash
echo "\$(basename "\$0") \$*" >> "$work/calls.log"
# The readiness probes accept only a 200.
[ "\$(basename "\$0")" = curl ] && echo 200
# \`sudo <cmd>\` must run the stubbed <cmd>, not swallow it.
if [ "\$(basename "\$0")" = sudo ]; then shift; exec "\$@"; fi
exit 0
STUB
  chmod +x "$work/bin/$tool"
done

# Wait for the log to contain a marker; the forwarder is backgrounded and lands after the script exits.
wait_for_call() {
  for _ in $(seq 1 15); do
    grep -q "$1" "$work/calls.log" 2>/dev/null && return 0
    sleep 0.2
  done
  return 1
}

echo "provision.sh"

set +e
( cd "$work/workspace" \
  && PATH="$work/bin:$PATH" RUNNER_TEMP="$work/runner" DEMODATA=false GITHUB_ENV="$work/env" \
     bash "$STEP" >"$work/out.log" 2>&1 )
status=$?
set -e
assert_equals "0" "$status" "completes against a stubbed toolchain"

calls=$(cat "$work/calls.log")

# The sandbox mounts GITHUB_WORKSPACE read-write, so a shop left inside it is a tree the agent could
# rewrite before the host runs anything from it.
if [ -d "$work/workspace/shop" ]; then
  echo "  FAIL left the shop inside the workspace"; FAIL=$((FAIL + 1))
else
  echo "  ok   moves the shop out of the workspace"; PASS=$((PASS + 1))
fi
assert_contains "SW_SHOT_SHOP_DIR=$work/runner/sw-screenshot-shop" "$(cat "$work/env")" "reports the relocated shop to later host steps"

# setup-shopware's own server holds the port against the old path; started again from the new path
# it would fail with "address already in use".
assert_contains "server:stop" "$calls" "stops the inherited server before moving"
assert_contains "server:start --port=8000" "$calls" "starts the shop on an explicit port"

assert_contains "dal:refresh:index" "$calls" "refreshes the indices"
assert_contains "theme:compile" "$calls" "compiles the theme"

if grep -q "framework:demodata" <<<"$calls"; then
  echo "  FAIL DEMODATA=false still generated demo data"; FAIL=$((FAIL + 1))
else
  echo "  ok   DEMODATA=false skips demo data"; PASS=$((PASS + 1))
fi

# gh aw's MCP gateway owns 8080 and the sandbox reaches only 80/443/8080, so the shop is published on
# 80 by a forwarder rather than bound there directly.
wait_for_call "TCP-LISTEN:80" || true
forward=$(cat "$work/calls.log")
assert_contains "TCP-LISTEN:80" "$forward" "publishes the shop on the port the sandbox can reach"
assert_contains "TCP:127.0.0.1:8000" "$forward" "forwards to the unprivileged port the shop listens on"

# The agent is sandboxed, where localhost is the sandbox rather than the runner; port 80 is implied.
assert_contains "APP_URL=http://host.docker.internal" "$(cat "$work/env")" "exports the sandbox-reachable url"
if grep -qE '^SHOP_DIR=' "$work/env"; then
  echo "  FAIL exported SHOP_DIR into the agent's environment"; FAIL=$((FAIL + 1))
else
  echo "  ok   keeps SHOP_DIR out of the agent's environment"; PASS=$((PASS + 1))
fi

# ProductGenerator draws up to 3 distinct download media, and only every 30th generated file is one;
# below 61 the run fails on some random rolls and passes on others.
media=$(sed -n 's/.*DEMODATA_MEDIA:-\([0-9]*\).*/\1/p' "$STEP" | head -1)
if [ -n "$media" ] && [ "$media" -ge 61 ]; then
  echo "  ok   default media count covers 3 distinct download files"; PASS=$((PASS + 1))
else
  echo "  FAIL default media count is ${media:-unset}, needs >= 61"; FAIL=$((FAIL + 1))
fi

# A streaming command in the diagnostics hangs the step until the job is killed, which is far worse
# than the failure it was meant to explain.
if grep -vE '^\s*#' "$STEP" | grep -qE 'server:log|tail -f|journalctl -f|docker logs -f'; then
  echo "  FAIL diagnostics use a streaming command that never returns"; FAIL=$((FAIL + 1))
else
  echo "  ok   diagnostics only run commands that terminate"; PASS=$((PASS + 1))
fi

finish
