# shellcheck shell=bash
# SC2154: status, output and stderr are set by the bats run helper.
# shellcheck disable=SC2154

RUN_SH="$BATS_TEST_DIRNAME/run.sh"

# Runs run.sh with the given arguments and a fresh output directory, and stores the JSON path in JSON.
run_json() {
    RUNS=$((${RUNS:-0} + 1))
    run --separate-stderr "$RUN_SH" "$@" --out "$BATS_TEST_TMPDIR/out.$RUNS"
    [ "$status" -eq 0 ] || {
        printf 'run.sh failed with %s: %s\n' "$status" "$stderr" >&2
        return 1
    }
    JSON=$output
}

# jq -r over the last JSON.
q() {
    jq -r "$1" "$JSON"
}
