#!/usr/bin/env bash
# Shared grader helpers for the reproduce-skill evals. Mounted into each task's
# workspace as ./_lib.sh and sourced by <task>/grader.sh.
#
#   source "$(dirname "$0")/_lib.sh"
#   load_output            # asserts output.json exists and parses
#   check name 'jq-expr'   # registers one predicate
#   emit_result            # prints {"score":..., "details":..., "checks":[...]}

OUT="${OUT:-output.json}"
declare -a _CHECKS=()
declare -i _PASSED=0
declare -i _TOTAL=0

load_output() {
    if [[ ! -f "$OUT" ]]; then
        echo "{\"score\":0,\"details\":\"$OUT missing — agent did not produce expected output file\",\"checks\":[{\"name\":\"output-exists\",\"passed\":false,\"message\":\"$OUT not found\"}]}"
        exit 0
    fi
    if ! jq -e . "$OUT" >/dev/null 2>&1; then
        echo "{\"score\":0,\"details\":\"$OUT is not valid JSON\",\"checks\":[{\"name\":\"output-parses\",\"passed\":false,\"message\":\"jq parse failed\"}]}"
        exit 0
    fi
}

check() {
    local name="$1" expr="$2"
    _TOTAL=$((_TOTAL + 1))
    if jq -e "$expr" "$OUT" >/dev/null 2>&1; then
        _PASSED=$((_PASSED + 1))
        _CHECKS+=("$(jq -cn --arg n "$name" '{name:$n, passed:true, message:"ok"}')")
    else
        _CHECKS+=("$(jq -cn --arg n "$name" --arg e "$expr" '{name:$n, passed:false, message:("predicate failed: " + $e)}')")
    fi
}

# analysis.json (the repro plan) shape per references/SCHEMA.md.
check_schema_analysis() {
    check "schema-version-1" '.schema_version == "1"'
    check "layer-valid"      '.layer as $l | ["service","store-api","admin-api","storefront-ui","admin-ui"] | index($l) != null'
    check "executor-valid"   '.executor as $e | ["direct","http","playwright"] | index($e) != null'
    # storefront-ui may run in the browser (playwright) OR, for a server-rendered symptom,
    # via a functional render test (direct) / page GET (http). admin-ui is a client-rendered
    # SPA → playwright only. *-api → http; service → direct.
    check "executor-matches-layer" '
        (.layer == "service"    and .executor == "direct")
        or ((.layer == "store-api" or .layer == "admin-api") and .executor == "http")
        or (.layer == "admin-ui"     and .executor == "playwright")
        or (.layer == "storefront-ui" and (.executor == "playwright" or .executor == "direct" or .executor == "http"))'
    check "build-profile-object" '(.build_profile | type) == "object"'
    check "targets-valid"    '(.targets // []) | (type == "array") and (all(.[]; . == "reported" or . == "trunk"))'
}

emit_result() {
    local score
    if (( _TOTAL == 0 )); then score="0.00"
    else score=$(awk "BEGIN {printf \"%.2f\", $_PASSED/$_TOTAL}"); fi
    local checks_arr
    checks_arr=$(printf '%s\n' "${_CHECKS[@]}" | jq -cs '.')
    jq -cn --argjson score "$score" --arg details "$_PASSED/$_TOTAL checks passed" --argjson checks "$checks_arr" \
        '{score:$score, details:$details, checks:$checks}'
}
