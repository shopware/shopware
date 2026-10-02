#!/usr/bin/env bash
# Run the source swap on the host, on request from the sandboxed agent.
#
# The swap needs the host PHP — the sandbox ships a build without PDO or iconv, so `bin/console`
# cannot run there. Rather than widening the sandbox, the agent drops a request and this worker does
# the work outside it.
#
# The entire interface is one word, `head` or `base`, matched against a literal allowlist. Nothing
# else crosses: no paths, no arguments, no environment. Both halves of what then executes sit outside
# GITHUB_WORKSPACE — the scripts are copied to a read-only directory before the agent starts, and the
# shop was moved out by provision.sh — so the agent can rewrite neither the script nor the tree it
# acts on. A request naming anything else is discarded.
#
# Usage: swap-worker.sh <start|stop>
# Env: REQUEST_DIR (required, the workspace path both sides can see), SHOP_DIR (required).
set -euo pipefail

STEPS_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
: "${REQUEST_DIR:?REQUEST_DIR is required}"
PID_FILE="${RUNNER_TEMP:-/tmp}/sw-screenshot-swap-worker.pid"

REQUEST="${REQUEST_DIR}/request"
RESULT="${REQUEST_DIR}/result"
LOG="${REQUEST_DIR}/log"

case "${1:-}" in
  start)
    : "${SHOP_DIR:?SHOP_DIR is required}"

    # Run from a copy the agent cannot reach. $STEPS_DIR is inside the workspace, which the sandbox
    # mounts read-write, so executing from there would let the agent choose what the host runs.
    SAFE_STEPS="${RUNNER_TEMP:-/tmp}/sw-screenshot-steps"
    rm -rf "$SAFE_STEPS"
    cp -R "$STEPS_DIR" "$SAFE_STEPS"
    chmod -R a-w "$SAFE_STEPS"

    mkdir -p "$REQUEST_DIR"
    rm -f "$REQUEST" "$RESULT" "$LOG"

    (
      while true; do
        if [ -f "$REQUEST" ]; then
          target=$(tr -dc 'a-z' < "$REQUEST" | head -c 8)
          rm -f "$REQUEST"

          case "$target" in
            head|base)
              if SHOP_DIR="$SHOP_DIR" bash "$SAFE_STEPS/swap.sh" "$target" > "$LOG" 2>&1; then
                printf '0' > "$RESULT"
              else
                printf '%s' "$?" > "$RESULT"
              fi
              ;;
            *)
              printf 'refused: a swap target is "head" or "base", got %s\n' "${target:-<empty>}" > "$LOG"
              printf '64' > "$RESULT"
              ;;
          esac
        fi
        sleep 2
      done
    ) &

    echo $! > "$PID_FILE"
    echo "swap worker listening on ${REQUEST_DIR}"
    ;;
  stop)
    [ -f "$PID_FILE" ] || exit 0
    # The loop may already have exited with the job; a stale pid is not a failure to stop.
    kill "$(cat "$PID_FILE")" 2>/dev/null || true
    rm -f "$PID_FILE"
    ;;
  *)
    echo "usage: swap-worker.sh <start|stop>" >&2; exit 1
    ;;
esac
