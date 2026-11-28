#!/usr/bin/env bash
# Recall check: runs run.sh against three real base merges of the shopware/shopware repository and
# asserts items known to be in them. Needs the full history, so it is not part of the Bats tests in scripts/run.sh.
# Usage: recall.sh <repository>. Exits non-zero when any assertion fails.
set -euo pipefail

RUN_SH="$(cd "$(dirname "${BASH_SOURCE[0]}")/../scripts" && pwd -P)/run.sh"
[[ $# -eq 1 ]] || {
    printf 'usage: recall.sh <repository>\n' >&2
    exit 2
}
cd "$1"
OUT=$(mktemp -d)
trap 'rm -rf "$OUT"' EXIT
FAILURES=0

# Runs run.sh in merge mode for $1, with the further run.sh arguments that follow, and stores the JSON path in JSON.
run_merge() {
    local merge=$1 start
    shift
    start=$(date +%s)
    JSON=$("$RUN_SH" --merge "$merge" --head "$merge" --base "$merge^2" --out "$OUT/$merge$*" "$@")
    printf 'TIME %s %s %ss\n' "$merge" "$*" "$(($(date +%s) - start))"
}

# Prints PASS or FAIL for the description $1 depending on whether the jq filter $2 yields true.
check() {
    if [[ $(jq -r "$2" "$JSON") == true ]]; then
        printf 'PASS %s\n' "$1"
        return 0
    fi
    printf 'FAIL %s\n' "$1"
    FAILURES=$((FAILURES + 1))
}

run_merge 739cc2fcc10
check '739cc2fcc10 mode is merge' '.scope.mode == "merge"'
check '739cc2fcc10 has 3 commits' '.scope.commit_count == 3'
check '739cc2fcc10 public API snapshot item owns 18 outputs, all under public-api/module/sw-experience-studio/' \
    '[.items[] | select(.class == "generated-artifact" and .path == "admin-public-api") | .generated_owned
      | length == 18 and all(contains("/public-api/module/sw-experience-studio/"))] == [true]'
check '739cc2fcc10 dependency twig/twig ^3.29.0 -> ^3.31.1' \
    'any(.items[]; .class == "dependency" and .detail == "twig/twig ^3.29.0 -> ^3.31.1")'
check '739cc2fcc10 dependency shopware/conflicts 0.6.3 -> 0.6.4' \
    'any(.items[]; .class == "dependency" and .detail == "shopware/conflicts 0.6.3 -> 0.6.4")'
check '739cc2fcc10 gate item for the Administration eslint.config.ts' \
    'any(.items[]; .class == "gate" and .path == "src/Administration/Resources/app/administration/eslint.config.ts")'

run_merge db71347e88d
check 'db71347e88d has 189 commits' '.scope.commit_count == 189'
# No WriteOutcome file was added in this range; XmlParser was added in it and migrated to.
check 'db71347e88d reusable-surface XmlParser with migrated call sites and before token XmlUtils' \
    'any(.items[]; .class == "reusable-surface" and .detail == "XmlParser" and .rank > 0 and any(.before_tokens[]; .token == "XmlUtils"))'

run_merge 6bb4604ce31
check '6bb4604ce31 has 368 commits' '.scope.commit_count == 368'
check '6bb4604ce31 guideline item for the ADR of 2026-07-23' \
    'any(.items[]; .class == "guideline" and .path == "adr/2026-07-23-administration-http-client-compatibility-facade.md")'
check '6bb4604ce31 before token AxiosInstance on the http-client.types item' \
    'any(.items[]; .class == "reusable-surface" and .detail == "http-client.types" and any(.before_tokens[]; .token == "AxiosInstance"))'

run_merge 739cc2fcc10 --since 7147377736a
check '739cc2fcc10 since 7147377736a has 1554 commits' '.scope.commit_count == 1554'
check '739cc2fcc10 since 7147377736a has one item per class and path for gate, guideline, bc-convention and migration-tooling' \
    '[.items[] | select(.class == "gate" or .class == "guideline" or .class == "bc-convention" or .class == "migration-tooling")
      | [.class, .path]] | length == (unique | length) and (map(.[0]) | unique | length == 4)'
check '739cc2fcc10 since 7147377736a has neither Core nor Framework as a before token' \
    'all(.items[].before_tokens[]; .token != "Core" and .token != "Framework")'
check '739cc2fcc10 since 7147377736a before token AxiosInstance on the http-client.types item' \
    'any(.items[]; .class == "reusable-surface" and .detail == "http-client.types" and any(.before_tokens[]; .token == "AxiosInstance"))'
check '739cc2fcc10 since 7147377736a has no candidate in a package-lock.json, an SVG or a public-api snapshot' \
    '([.items[].candidates[]] | length > 0) and all(.items[].candidates[]; (.path | test("package-lock\\.json$|\\.svg$|public-api/")) | not)'
printf 'INFO candidate lines %s\n' "$(jq '[.items[].candidates | length] | add // 0' "$JSON")"
printf 'INFO JSON bytes %s\n' "$(wc -c <"$JSON" | tr -d ' ')"

((FAILURES == 0)) || exit 1
