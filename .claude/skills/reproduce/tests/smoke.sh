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

# --- staged precheck gate (bin/run-precheck.sh): a TRUSTED + conclusive http precheck
# decides the leg and skips the playwright run; an UNTRUSTED or INCONCLUSIVE precheck is
# corroboration only and playwright still runs. A guessed precheck never decides → the
# pipeline can never emit a false `reproduced` from a precheck.
decisive () { # decisive <trusted> <precheck_status>
  if [ "$1" = true ] && { [ "$2" = reproduced ] || [ "$2" = not_reproduced ]; }; then echo true; else echo false; fi
}
echo "staged precheck gate:"
check "trusted+reproduced -> decides leg"       true  "$(decisive true  reproduced)"
check "trusted+not_reproduced -> decides leg"   true  "$(decisive true  not_reproduced)"
check "trusted+inconclusive -> fall back"       false "$(decisive true  inconclusive)"
check "trusted+blocked -> fall back"            false "$(decisive true  blocked)"
check "untrusted+reproduced -> fall back"       false "$(decisive false reproduced)"
check "untrusted+not_reproduced -> fall back"   false "$(decisive false not_reproduced)"

# --- direct executor PHPUnit mapping (run-direct.sh via PHPUNIT_REPORT): a PASSING test must
# map to not_reproduced and NOT crash (regression: an unguarded grep aborted the script on a
# passing report), and a FAILING test must surface its message in evidence.failure_detail. ---
BIN="$(cd "$(dirname "$0")/../../../../.github/actions/repro/bin" 2>/dev/null && pwd || true)"
if [ -n "$BIN" ] && [ -f "$BIN/run-direct.sh" ]; then
  echo "direct executor (run-direct.sh):"
  TD=$(mktemp -d)
  echo '{"issue":1,"version":"v","script_path":"none","assertion":{}}' > "$TD/an.json"
  printf 'OK (1 test, 1 assertion)\n' > "$TD/ok.txt"
  ( cd "$TD" && ANALYSIS=an.json OUT=ok.json TARGET=trunk PHPUNIT_REPORT=ok.txt bash "$BIN/run-direct.sh" >/dev/null 2>&1 ) || true
  check "passing test -> not_reproduced (no crash)" not_reproduced "$(jq -r '.status // "CRASH"' "$TD/ok.json" 2>/dev/null || echo CRASH)"
  check "passing test -> no failure_detail"         ""             "$(jq -r '.evidence.failure_detail' "$TD/ok.json" 2>/dev/null)"
  printf '1) Some\\Test::method\nsymptom: boom happened\n\n/p:1\n\nFAILURES!\nTests: 1, Failures: 1.\n' > "$TD/f.txt"
  ( cd "$TD" && ANALYSIS=an.json OUT=f.json TARGET=trunk PHPUNIT_REPORT=f.txt bash "$BIN/run-direct.sh" >/dev/null 2>&1 ) || true
  check "failing test -> reproduced"                reproduced "$(jq -r '.status // "CRASH"' "$TD/f.json" 2>/dev/null || echo CRASH)"
  check "failing test -> message in failure_detail" yes "$(jq -r 'if (.evidence.failure_detail // "") | test("symptom: boom happened") then "yes" else "no" end' "$TD/f.json" 2>/dev/null)"
  rm -rf "$TD"
fi

[ "$fail" = 0 ] && echo "PASS" || { echo "FAILURES"; exit 1; }
