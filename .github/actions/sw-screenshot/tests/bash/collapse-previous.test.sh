#!/usr/bin/env bash
# Collapsing keeps exactly the newest comment visible. Getting it wrong either leaves stale
# screenshots on the issue or hides the current ones.
set -euo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
source "$HERE/lib.sh"

STEP="$HERE/../../steps/collapse-previous.sh"

# Stubs `gh`: the listing returns $1 node ids, and every graphql mutation is recorded.
run_with() {
  local ids=$1 work
  work=$(mktemp -d)
  mkdir -p "$work/bin"

  cat > "$work/bin/gh" <<STUB
#!/usr/bin/env bash
case "\$*" in
  *graphql*)
      # The id arrives as a trailing "-f id=<value>" argument.
      for arg in "\$@"; do case "\$arg" in id=*) echo "\${arg#id=}" >> "$work/minimized.log" ;; esac; done
      echo '{}' ;;
  *comments*) printf '%s\n' $ids ;;
  *) echo "" ;;
esac
STUB
  chmod +x "$work/bin/gh"

  PATH="$work/bin:$PATH" ISSUE=30 REPO=o/r GH_TOKEN=x bash "$STEP" > "$work/out.log" 2>&1
  echo "$work"
}

echo "collapse-previous.sh"

work=$(run_with "")
assert_contains "nothing to collapse" "$(cat "$work/out.log")" "no comments is a no-op"

work=$(run_with "c1")
assert_contains "nothing to collapse" "$(cat "$work/out.log")" "a single comment is left visible"

# Listings come back oldest-first, so the last id is the current run's and must survive.
work=$(run_with "c1 c2 c3")
minimized=$(cat "$work/minimized.log" 2>/dev/null | tr '\n' ' ')
assert_contains "c1" "$minimized" "collapses the oldest comment"
assert_contains "c2" "$minimized" "collapses the middle comment"
if grep -q "c3" <<<"$minimized"; then
  echo "  FAIL collapsed the newest comment"; FAIL=$((FAIL + 1))
else
  echo "  ok   leaves the newest comment visible"; PASS=$((PASS + 1))
fi

# bash 3.2 (macOS) has no mapfile; the script must stay runnable there.
if grep -q "mapfile" "$STEP"; then
  echo "  FAIL uses mapfile, which bash 3.2 lacks"; FAIL=$((FAIL + 1))
else
  echo "  ok   avoids bash-4-only builtins"; PASS=$((PASS + 1))
fi

finish
