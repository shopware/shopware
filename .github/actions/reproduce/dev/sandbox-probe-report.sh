#!/usr/bin/env bash
# Host-side renderer for the sandbox probe (runs OUTSIDE the sandbox, in a post-step).
#
# Reads the probe's report, renders a pass/fail table into the job summary, and exits non-zero when
# any wall remains — a RED probe run is the deliverable (each ❌ is a wall to clear before the
# sandbox can be flipped on for reproduce.md). See dev/sandbox-handoff.md §4a.
#
# The workspace report arriving here is itself the wall-#7 (workspace handoff) test; if it is
# missing we fall back to the PROBE-JSON line the script also prints to the agent log, and flag the
# handoff as broken (this is the exact June failure mode we could never distinguish before).
set -uo pipefail

WORKSPACE="${GITHUB_WORKSPACE:-$PWD}"
REPORT="${WORKSPACE}/sandbox-probe-report.json"
LOG="${AGENT_LOG:-/tmp/gh-aw/agent-stdio.log}"
SUMMARY="${GITHUB_STEP_SUMMARY:-/dev/stdout}"

handoff_ok=true
if [ ! -s "$REPORT" ]; then
  handoff_ok=false
  if [ -s "$LOG" ]; then
    line=$(grep -a 'PROBE-JSON:' "$LOG" | tail -n1 | sed 's/^.*PROBE-JSON: //')
    if [ -n "${line:-}" ]; then
      REPORT="${WORKSPACE}/sandbox-probe-report.fallback.json"
      printf '%s\n' "$line" > "$REPORT"
    fi
  fi
fi

if [ ! -s "$REPORT" ] || ! jq -e . "$REPORT" >/dev/null 2>&1; then
  {
    echo "# 🧱 Sandbox probe — NO RESULT"
    echo
    echo "The probe produced neither a workspace \`sandbox-probe-report.json\` nor a recoverable"
    echo "\`PROBE-JSON\` line in the agent log. The script never ran to completion — the agent or the"
    echo "sandbox failed to start. Check the agent step logs and \`/tmp/gh-aw/agent-stdio.log\`."
  } >> "$SUMMARY"
  echo "::error::sandbox probe produced no result (script never ran)"
  exit 1
fi

total=$(jq -r '.summary.total // (.checks|length)' "$REPORT")
failed=$(jq -r '.summary.failed // ([.checks[]|select(.ok==false)]|length)' "$REPORT")

{
  echo "# 🧱 Sandbox probe results"
  echo
  if [ "$handoff_ok" = true ]; then
    echo "- **Workspace handoff (wall #7): ✅ working** — \`sandbox-probe-report.json\` arrived on the host."
  else
    echo "- **Workspace handoff (wall #7): ❌ BROKEN** — no workspace file; results were recovered from"
    echo "  the agent log. This is the June failure mode (agent ran, bundle never reached the host)."
  fi
  echo "- **Checks:** ${failed} of ${total} failed the healthy-sandbox expectation."
  echo
  echo "| Wall | Check | OK | Detail |"
  echo "|------|-------|:--:|--------|"
  jq -r '.checks[] | "| \(.wall) | `\(.check)` | \(if .ok then "✅" else "❌" end) | \(.detail|gsub("\\|";"\\\\|")) |"' "$REPORT"
  echo
  echo "_A ❌ is a wall to clear before enabling the sandbox on reproduce.md; \`php\`/\`mysql\` absence is expected (✅). See \`dev/sandbox-handoff.md\` §3._"
} >> "$SUMMARY"

if [ "${failed:-1}" -gt 0 ] || [ "$handoff_ok" != true ]; then
  echo "::error::sandbox probe: ${failed} checks failed, handoff_ok=${handoff_ok} — walls remain (see job summary)"
  exit 1
fi
echo "sandbox probe: all checks green — the sandbox environment is ready for reproduce.md"
exit 0
