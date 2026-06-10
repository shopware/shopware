#!/usr/bin/env bash
# Render the GitHub comment (-> $OUT, default comment.md) and, when $GITHUB_STEP_SUMMARY is
# set, the job summary. Shared by reproduce + fix-verify; MODE selects leg names, the
# headline, and the verdict callouts. Reads leg statuses straight from the artifacts.
#
# Env: MODE(reproduce|fix-verify, default reproduce), ART(default artifacts), ISSUE,
#      VERDICT, FIX, UNSURE, RUN_URL, OUT(default comment.md).
set -euo pipefail

MODE=${MODE:-reproduce}; ART=${ART:-artifacts}; OUT=${OUT:-comment.md}
VERDICT=${VERDICT:-needs_human_review}; FIX=${FIX:-}; UNSURE=${UNSURE:-}; RUN_URL=${RUN_URL:-}
case "$MODE" in
  reproduce)  A=reported; B=trunk; SUBJECT="Reproduction — Issue #${ISSUE}" ;;
  fix-verify) A=base;     B=head;  SUBJECT="Fix verification — PR #${ISSUE}" ;;
  *) echo "::error::unknown MODE '$MODE'"; exit 1 ;;
esac
AF="$ART/repro-$A/result.json"; BF="$ART/repro-$B/result.json"
AN="$ART/analysis/analysis.json"; ATTR="$ART/attribution/attribution.json"
LAYER=$(jq -r .layer "$AN" 2>/dev/null || echo unknown)
have_a=0; [ -f "$AF" ] && have_a=1
have_b=0; [ -f "$BF" ] && have_b=1
as="null"; [ -f "$AF" ] && as=$(jq -r .status "$AF")
bs="null"; [ -f "$BF" ] && bs=$(jq -r .status "$BF")

# Dedup: the generated script is identical across legs for direct/playwright (authored ONCE)
# and usually http; only http can vary (per-leg resolved ids). Show it once when they match.
same=0; maxlines=0
for f in "$AF" "$BF"; do [ -f "$f" ] || continue
  n=$(jq -r '.evidence.script // ""' "$f" | wc -l | tr -d ' '); [ "$n" -gt "$maxlines" ] && maxlines=$n; done
if [ "$have_a" = 1 ] && [ "$have_b" = 1 ]; then
  cmp -s <(jq -r '.evidence.script // ""' "$AF") <(jq -r '.evidence.script // ""' "$BF") && same=1; fi

emit_script () { # <result.json> <label-suffix> <mode: comment|full>
  local f="$1" label="$2" mode="$3" lang nlines
  lang=$(jq -r '.evidence.script_lang // "sh"' "$f")
  nlines=$(jq -r '.evidence.script // ""' "$f" | wc -l | tr -d ' ')
  [ "$nlines" -gt 0 ] || return 0
  echo "**Repro script${label}** (\`${lang}\`)"
  if [ "$mode" = comment ] && [ "$nlines" -gt 60 ]; then
    echo "_${nlines} lines — in the \`repro-*\` run [artifact](${RUN_URL})._"
  else echo "\`\`\`${lang}"; jq -r .evidence.script "$f"; echo '```'; fi
}
render_scripts () { # <mode: comment|full>
  local mode="$1"
  if   [ "$have_a" = 1 ] && [ "$have_b" = 1 ] && [ "$same" = 1 ]; then emit_script "$AF" " (run unchanged on both legs)" "$mode"
  elif [ "$have_a" = 1 ] && [ "$have_b" = 1 ]; then emit_script "$AF" " — $A" "$mode"; echo; emit_script "$BF" " — $B" "$mode"
  elif [ "$have_a" = 1 ]; then emit_script "$AF" "" "$mode"
  elif [ "$have_b" = 1 ]; then emit_script "$BF" "" "$mode"; fi
}
leg_section () { # <leg name>
  local t="$1" f st br ex ac
  f="$ART/repro-$t/result.json"; [ -f "$f" ] || return 0
  st=$(jq -r .status "$f"); br=$(jq -r '.blocked_reason // ""' "$f")
  echo
  if [ -n "$br" ] && [ "$br" != "null" ]; then echo "### ${t} — \`${st}\`"; echo; echo "> ⚠️ ${br}"
  else ex=$(jq -r .assertion.expect "$f"); ac=$(jq -r .assertion.actual "$f"); echo "### ${t} — \`${st}\` (expected ${ex}, got ${ac})"; fi
  echo "Reporter: \`$(jq -r .evidence.reporter_output "$f")\`"
  [ "$(jq -r .executor "$f")" = playwright ] && echo "📊 Interactive Playwright report + trace/video: \`repro-${t}\` artifact of the [run](${RUN_URL}) — open \`playwright-report/index.html\`."
}

{
  echo "## ${SUBJECT}: \`${VERDICT}\`"; echo
  echo "Layer \`${LAYER}\` · ${A}: \`${as}\` · ${B}: \`${bs}\` · [run](${RUN_URL})"; echo
  if jq -e '.scenario | arrays and length > 0' "$AN" >/dev/null 2>&1; then
    echo "**Scenario**"; jq -r '.scenario[] | "1. " + .' "$AN"; echo; fi
  case "$VERDICT" in
    fixed_on_trunk)      [ -n "$FIX" ] && echo "**Likely fix (backport candidate):** ${FIX}" ;;
    fix_verified)        echo "> ✅ **Fix verified**: the symptom is present on \`base\` (without the fix) and gone on \`head\` (with it).${FIX:+ Derived from ${FIX}.}" ;;
    fix_ineffective)     echo "> ❌ **Fix ineffective**: the symptom still reproduces on \`head\` (with the fix applied) — the change does not remove it." ;;
    test_does_not_guard) echo "> ⚠️ **Test does not guard the bug**: the repro passes even on \`base\` (without the fix), so it would NOT catch a regression. Strengthen the test, or the symptom isn't actually being exercised." ;;
    introduces_symptom)  echo "> ❌ **Introduces the symptom**: \`head\` reproduces a symptom that \`base\` does not." ;;
    regression)          echo "> ⚠️ **Regression**: not reproduced on the reported version but reproduced on trunk. Confirm the reported leg actually exercised the symptom (a missing fixture can cause a false negative)." ;;
    needs_human_review)  echo "> 🟡 **Needs human review**: the verdict is not trusted automatically${UNSURE:+ — ${UNSURE}}. The leg evidence below is informative but unconfirmed; confirm against the steps before acting." ;;
  esac
  if [ -f "$ATTR" ]; then KIND=$(jq -r .kind "$ATTR"); CMT=$(jq -r .likely_commit "$ATTR"); RSN=$(jq -r .reasoning "$ATTR"); echo "**Likely ${KIND} commit:** \`${CMT}\` — ${RSN}"; fi
  leg_section "$A"; leg_section "$B"
  echo
  render_scripts comment
} > "$OUT"

if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
  { cat "$OUT"
    if [ "$maxlines" -gt 60 ]; then echo; echo "<details><summary>Full generated repro script</summary>"; echo; render_scripts full; echo; echo "</details>"; fi
  } >> "$GITHUB_STEP_SUMMARY"
fi
cat "$OUT"
