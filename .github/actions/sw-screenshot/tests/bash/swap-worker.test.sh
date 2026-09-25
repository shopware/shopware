#!/usr/bin/env bash
# The worker is the only path from the sandboxed agent to host execution, so its two guarantees are
# tested rather than assumed: it accepts nothing but "head"/"base", and it runs the copy of swap.sh
# that the agent cannot reach.
set -euo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
source "$HERE/lib.sh"

WORKER="$HERE/../../steps/swap-worker.sh"

work=$(mktemp -d)
steps="$work/workspace/steps"
mkdir -p "$steps" "$work/runner" "$work/shop"
cp "$WORKER" "$steps/swap-worker.sh"

# Stand-in for the real swap: records that it ran, and which copy ran.
cat > "$steps/swap.sh" <<'STUB'
#!/usr/bin/env bash
echo "original swap.sh ran with target=$1"
STUB

export RUNNER_TEMP="$work/runner"
REQUEST_DIR="$work/workspace/.swap" SHOP_DIR="$work/shop" bash "$steps/swap-worker.sh" start >/dev/null

# Wait for a result file, then report status and log.
request() {
  rm -f "$work/workspace/.swap/result" "$work/workspace/.swap/log"
  printf '%s' "$1" > "$work/workspace/.swap/request"
  for _ in $(seq 1 25); do
    [ -f "$work/workspace/.swap/result" ] && return 0
    sleep 0.4
  done
  return 1
}

echo "swap-worker.sh"

request head && assert_equals "0" "$(cat "$work/workspace/.swap/result")" "accepts head"
assert_contains "target=head" "$(cat "$work/workspace/.swap/log")" "runs the swap for head"

# Anything that is not exactly head or base must never reach a shell.
for hostile in 'head; touch /tmp/sw-shot-pwned' '../../etc/passwd' '$(touch /tmp/sw-shot-pwned)' ''; do
  request "$hostile" || true
  code=$(cat "$work/workspace/.swap/result" 2>/dev/null || echo missing)
  if [ "$code" = "64" ]; then
    echo "  ok   refuses ${hostile:-<empty>}"; PASS=$((PASS + 1))
  else
    echo "  FAIL accepted ${hostile:-<empty>} (result ${code})"; FAIL=$((FAIL + 1))
  fi
done

if [ -e /tmp/sw-shot-pwned ]; then
  echo "  FAIL a hostile request executed"; FAIL=$((FAIL + 1)); rm -f /tmp/sw-shot-pwned
else
  echo "  ok   no hostile request executed"; PASS=$((PASS + 1))
fi

# The agent can rewrite the workspace copy; the worker must not be running from it.
cat > "$steps/swap.sh" <<'STUB'
#!/usr/bin/env bash
echo "TAMPERED swap.sh ran"
STUB

request base && assert_equals "0" "$(cat "$work/workspace/.swap/result")" "still accepts base after tampering"
log=$(cat "$work/workspace/.swap/log")
if grep -q TAMPERED <<<"$log"; then
  echo "  FAIL ran the workspace copy the agent rewrote"; FAIL=$((FAIL + 1))
else
  echo "  ok   ignores edits to the workspace copy of swap.sh"; PASS=$((PASS + 1))
fi

REQUEST_DIR="$work/workspace/.swap" bash "$steps/swap-worker.sh" stop || true
finish
