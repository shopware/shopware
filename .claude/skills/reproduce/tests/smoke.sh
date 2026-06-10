#!/usr/bin/env bash
# Smoke test for the reproduce pipeline's decision logic.
#
# Tests the two pure contracts from references/SCHEMA.md that are most likely to
# regress: the target matrix (dedup + "not on manual rerun") and the verdict map.
# No GitHub, no Docker — just bash. Run: bash .claude/skills/reproduce/tests/smoke.sh
set -euo pipefail

fail=0
check () { # check <label> <expected> <actual>
  if [ "$2" = "$3" ]; then echo "  ok   $1"; else echo "  FAIL $1: expected '$2' got '$3'"; fail=1; fi
}

# --- target matrix: one leg if manual OR reported == trunk, else two ---
targets () { # targets <skip_reported> <reported_version> <trunk_version>
  if [ "$1" = "true" ] || [ "$2" = "$3" ]; then echo '["trunk"]'; else echo '["reported","trunk"]'; fi
}
echo "matrix (dedup + not-on-manual-rerun):"
check "normal -> two legs"        '["reported","trunk"]' "$(targets false 6.6.10.0 trunk)"
check "skip_reported -> one leg"   '["trunk"]'            "$(targets true  6.6.10.0 trunk)"
check "reported==trunk -> one leg" '["trunk"]'           "$(targets false trunk    trunk)"

# --- verdict map (first match wins); single-leg runs have reported == null ---
# unsure = analyze flagged a weak/blocked plan (blocked_reason or 0.4 <= confidence < 0.7;
# below 0.4 bails before provision, so the verdict job never sees it).
verdict () { # verdict <reported_status> <trunk_status> [unsure]
  local rep="$1" tru="$2" unsure="${3:-false}"
  if   [ "$rep" = blocked ] || [ "$tru" = blocked ];               then echo blocked
  elif [ "$unsure" = true ];                                       then echo needs_human_review
  elif [ "$rep" = inconclusive ] || [ "$tru" = inconclusive ];     then echo needs_human_review
  elif [ "$rep" = reproduced ]     && [ "$tru" = reproduced ];     then echo live_bug
  elif [ "$rep" = reproduced ]     && [ "$tru" = not_reproduced ]; then echo fixed_on_trunk
  elif [ "$rep" = not_reproduced ] && [ "$tru" = reproduced ];     then echo regression
  elif [ "$rep" = not_reproduced ] && [ "$tru" = not_reproduced ]; then echo not_reproducible
  elif [ "$rep" = null ] && [ "$tru" = reproduced ];               then echo live_bug
  elif [ "$rep" = null ] && [ "$tru" = not_reproduced ];           then echo not_reproducible
  else echo needs_human_review; fi
}
echo "verdict map:"
check "both reproduced -> live_bug"            live_bug         "$(verdict reproduced reproduced)"
check "reported only -> fixed_on_trunk"        fixed_on_trunk   "$(verdict reproduced not_reproduced)"
check "regression (trunk only) -> regression"  regression       "$(verdict not_reproduced reproduced)"
check "neither -> not_reproducible"            not_reproducible "$(verdict not_reproduced not_reproduced)"
check "any blocked -> blocked"                 blocked          "$(verdict blocked reproduced)"
check "blocked beats unsure"                   blocked          "$(verdict blocked reproduced true)"
check "single-leg trunk repro -> live_bug"     live_bug         "$(verdict null reproduced)"
check "single-leg trunk clean -> not_repro"    not_reproducible "$(verdict null not_reproduced)"
check "inconclusive -> needs_human_review"     needs_human_review "$(verdict inconclusive not_reproduced)"
check "low-confidence plan -> needs_human"     needs_human_review "$(verdict reproduced reproduced true)"

# --- fix-verify verdict map: drive the REAL bin/verdict.sh (MODE=fix-verify) ---
# legs: base (fix absent) , head (fix present). Builds a temp artifacts dir and runs the script.
VS="$(cd "$(dirname "$0")/../../../../.github/actions/repro/bin" && pwd)/verdict.sh"
fv () { # fv <base_status> <head_status> [conf]
  local d; d=$(mktemp -d)
  mkdir -p "$d/repro-base" "$d/repro-head" "$d/analysis"
  [ "$1" != "-" ] && echo "{\"status\":\"$1\"}" > "$d/repro-base/result.json"
  [ "$2" != "-" ] && echo "{\"status\":\"$2\"}" > "$d/repro-head/result.json"
  echo "{\"confidence\":${3:-0.9}}" > "$d/analysis/analysis.json"
  MODE=fix-verify ART="$d" bash "$VS" 2>/dev/null | grep '^verdict=' | cut -d= -f2
}
echo "fix-verify verdict map (real bin/verdict.sh):"
check "base repro, head clean -> fix_verified"      fix_verified        "$(fv reproduced not_reproduced)"
check "both reproduced -> fix_ineffective"          fix_ineffective     "$(fv reproduced reproduced)"
check "both clean -> test_does_not_guard"           test_does_not_guard "$(fv not_reproduced not_reproduced)"
check "base clean, head repro -> introduces"        introduces_symptom  "$(fv not_reproduced reproduced)"
check "any blocked -> blocked"                       blocked             "$(fv blocked not_reproduced)"
check "inconclusive -> needs_human_review"           needs_human_review  "$(fv inconclusive not_reproduced)"
check "low confidence -> needs_human_review"         needs_human_review  "$(fv reproduced not_reproduced 0.5)"

[ "$fail" = 0 ] && echo "PASS" || { echo "FAILURES"; exit 1; }
