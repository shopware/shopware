#!/usr/bin/env bash
# Shared grader helpers. Lives at tests/_lib.sh; mounted into each task's
# workspace as ./_lib.sh and sourced by <bucket>/.../grader.sh.
#
# Pattern:
#   source "$(dirname "$0")/_lib.sh"
#   load_output            # asserts output.json exists and parses
#   check name 'jq-expr'   # registers one predicate
#   emit_result            # prints final {"score":..., "details":..., "checks":[...]}

OUT="${OUT:-output.json}"
declare -a _CHECKS=()
declare -i _PASSED=0
declare -i _TOTAL=0

_json_escape() {
    jq -Rn --arg s "$1" '$s'
}

load_output() {
    if [[ ! -f "$OUT" ]]; then
        echo "{\"score\":0,\"details\":\"$OUT missing — agent did not produce expected output file\",\"checks\":[{\"name\":\"output-exists\",\"passed\":false,\"message\":\"$OUT not found in workspace\"}]}"
        exit 0
    fi

    if ! jq -e . "$OUT" >/dev/null 2>&1; then
        echo "{\"score\":0,\"details\":\"$OUT is not valid JSON\",\"checks\":[{\"name\":\"output-parses\",\"passed\":false,\"message\":\"jq parse failed\"}]}"
        exit 0
    fi
}

check() {
    local name="$1"
    local expr="$2"
    _TOTAL=$((_TOTAL + 1))

    if jq -e "$expr" "$OUT" >/dev/null 2>&1; then
        _PASSED=$((_PASSED + 1))
        _CHECKS+=("$(jq -cn --arg n "$name" '{name:$n, passed:true, message:"ok"}')")
    else
        _CHECKS+=("$(jq -cn --arg n "$name" --arg e "$expr" '{name:$n, passed:false, message:("predicate failed: " + $e)}')")
    fi
}

check_schema_merged() {
    check "schema-version-1"      '.schema_version == "1"'
    check "decision-valid"        '.decision as $d | ["comment","request_changes","block","needs_human_review"] | index($d) != null'
    check "risk-level-valid"      '.risk_level as $r | ["low","medium","high","critical"] | index($r) != null'
    check "findings-is-array"     '(.findings // []) | type == "array"'
    check "personas-run-is-array" '(.personas_run // []) | type == "array"'
}

check_schema_persona() {
    check "schema-version-1"   '.schema_version == "1"'
    check "persona-field"      'has("persona")'
    check "decision-valid"     '.decision as $d | ["comment","request_changes","block","needs_human_review"] | index($d) != null'
    check "risk-level-valid"   '.risk_level as $r | ["low","medium","high","critical"] | index($r) != null'
    check "findings-is-array"  '(.findings // []) | type == "array"'
}

personas_run_includes() {
    echo "(.personas_run // []) | index(\"$1\") != null"
}

personas_run_excludes() {
    echo "(.personas_run // []) | index(\"$1\") == null"
}

personas_skipped_includes() {
    echo "[(.personas_skipped // [])[] | .persona] | index(\"$1\") != null"
}

emit_result() {
    local score
    if (( _TOTAL == 0 )); then
        score="0.00"
    else
        score=$(awk "BEGIN {printf \"%.2f\", $_PASSED/$_TOTAL}")
    fi

    local checks_arr
    checks_arr=$(printf '%s\n' "${_CHECKS[@]}" | jq -cs '.')

    jq -cn \
        --argjson score "$score" \
        --arg details "$_PASSED/$_TOTAL checks passed" \
        --argjson checks "$checks_arr" \
        '{score:$score, details:$details, checks:$checks}'
}
