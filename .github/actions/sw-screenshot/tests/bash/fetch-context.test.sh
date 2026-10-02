#!/usr/bin/env bash
# fetch-context.sh decides the run's mode. Getting that wrong sends the agent down the
# before/after path for a plain issue, or drops the swap for a pull request — so it is tested
# against a stubbed `gh` rather than trusted to the workflow.
set -euo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
source "$HERE/lib.sh"

STEP="$HERE/../../steps/fetch-context.sh"

# $1 selects issue or pr; a non-empty $2 makes the comments endpoint fail the way a rate limit does.
run_with_stub() {
  local kind=$1 fail=${2:-}
  local work status=0
  work=$(mktemp -d)

  mkdir -p "$work/bin" "$work/skills/modes"
  echo "ISSUE MODE FILE" > "$work/skills/modes/issue.md"
  echo "PR MODE FILE" > "$work/skills/modes/pr.md"

  cat > "$work/bin/gh" <<STUB
#!/usr/bin/env bash
# Minimal \`gh api\` stand-in: enough shape for the step, nothing more.
args="\$*"
case "\$args" in
  *"/issues/42"*"pull_request then"*) [ "$kind" = pr ] && echo true || echo false ;;
  *"/compare/"*)                      echo "mergebasesha111" ;;
  *"/pulls/42"* )
      case "\$args" in
        *".base.sha"*)  echo "basesha000" ;;
        *".body"*)      echo "Fixes #7" ;;
        *)              printf '## Pull request\n\n### Title\n\nA title\n\n### Body\n\nFixes #7\n' ;;
      esac ;;
  *"/issues/7"*)  printf '### Title\n\nLinked issue title\n\n### Body\n\nbody\n' ;;
  *"/issues/42/comments"*) [ -n "$fail" ] && exit 1; printf '### @someone\n\na comment\n' ;;
  *"/issues/42"*) printf '## Issue\n\n### Title\n\nAn issue\n\n### Body\n\nbody\n' ;;
  *) echo "" ;;
esac
STUB
  chmod +x "$work/bin/gh"

  ( cd "$work" && PATH="$work/bin:$PATH" NUMBER=42 REPO=o/r GH_TOKEN=x SKILL_DIR="$work/skills" \
      GITHUB_OUTPUT="$work/output" bash "$STEP" >/dev/null 2>&1 ) || status=$?
  printf '%s' "$status" > "$work/status"

  echo "$work"
}

echo "fetch-context.sh"

work=$(run_with_stub issue)
assert_equals "ISSUE MODE FILE" "$(cat "$work/mode.md")" "issue selects the issue mode file"
assert_equals "42" "$(cat "$work/number.txt")" "issue records the number"
assert_contains "Mode: issue" "$(cat "$work/context.md")" "issue context names the mode"
assert_contains "provision_ref=trunk" "$(cat "$work/output")" "issue provisions trunk"
assert_contains "a comment" "$(cat "$work/context.md")" "issue context carries the comments"
[ -f "$work/base-sha.txt" ] && { echo "  FAIL issue must not write base-sha.txt"; FAIL=$((FAIL + 1)); } \
  || { echo "  ok   issue writes no base-sha.txt"; PASS=$((PASS + 1)); }

work=$(run_with_stub pr)
assert_equals "PR MODE FILE" "$(cat "$work/mode.md")" "pull request selects the pr mode file"
assert_equals "mergebasesha111" "$(cat "$work/base-sha.txt")" "pull request records the merge base"
assert_contains "provision_ref=mergebasesha111" "$(cat "$work/output")" "pull request provisions from the merge base"
assert_equals "refs/pull/42/head" "$(cat "$work/head-ref.txt")" "pull request records the head ref"
assert_contains "Linked issue #7" "$(cat "$work/context.md")" "pull request pulls in the linked issue"

# A swallowed failure reaches the agent as an issue with no comments on it, and it photographs
# whatever page it can guess at instead of the one the repro steps name.
work=$(run_with_stub issue fail)
assert_equals "1" "$(cat "$work/status")" "a failed comments fetch fails the step"

finish
