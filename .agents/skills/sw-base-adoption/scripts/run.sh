#!/usr/bin/env bash
# shellcheck shell=bats
# Collects the input of a base-adoption audit for the repository in the current directory:
# the intent items a base range introduced and the branch-owned lines that still show the earlier form.
# Prints the path of base-adoption-input.json. Exit codes: 0 success (noop included), 2 usage error, 1 failure.
# Its Bats tests follow the direct-execution guard at the end: bats scripts/run.sh

USAGE='run.sh --base <ref> [--head <ref>] [--merge <sha>] [--since <sha>] [--out <dir>]'

die() {
    printf 'error: %s\n' "$*" >&2
    exit 1
}

usage_die() {
    printf 'error: %s\nusage: %s\n' "$*" "$USAGE" >&2
    exit 2
}

g() {
    git -c core.quotePath=false "$@"
}

#######################################
# git grep with every config that shapes its output pinned, so the parsed format is fixed, and with
# gitattributes read from the searched tree, so attribute pathspecs follow that tree.
# Arguments:
#   the tree-ish to read gitattributes from, then git grep arguments
# Returns:
#   git grep's exit code: 0 match, 1 no match, above 1 error.
#######################################
g_grep() {
    local attr_source=$1
    shift
    git -c core.quotePath=false -c grep.column=false -c grep.lineNumber=false -c grep.fullName=false \
        -c color.grep=never -c grep.fallbackToNoIndex=false --attr-source="$attr_source" grep "$@"
}

#######################################
# Globals:
#   SEARCH_EXCLUDES (written): exclude pathspecs for files marked linguist-generated and for the
#   glob patterns of search-excludes.txt
#######################################
load_search_excludes() {
    local pattern
    SEARCH_EXCLUDES=(':(exclude,attr:linguist-generated=true)')
    while IFS= read -r pattern; do
        [[ -n $pattern && $pattern != \#* ]] || continue
        SEARCH_EXCLUDES+=(":(exclude,glob)$pattern")
    done <"$SCRIPT_DIR/search-excludes.txt"
}

#######################################
# Globals:
#   HEAD_REF BASE_REF MERGE_REF SINCE_REF OUT_DIR (written)
# Arguments:
#   the command line
#######################################
parse_args() {
    HEAD_REF=""
    BASE_REF=""
    MERGE_REF=""
    SINCE_REF=""
    OUT_DIR=""
    while (($# > 0)); do
        case $1 in
            -h | --help)
                printf 'usage: %s\n' "$USAGE"
                exit 0
                ;;
            --head | --base | --merge | --since | --out) ;;
            *) usage_die "unknown argument $1" ;;
        esac
        (($# >= 2)) && [[ -n $2 ]] || usage_die "missing value for $1"
        case $1 in
            --head) HEAD_REF=$2 ;;
            --base) BASE_REF=$2 ;;
            --merge) MERGE_REF=$2 ;;
            --since) SINCE_REF=$2 ;;
            --out) OUT_DIR=$2 ;;
        esac
        shift 2
    done
    [[ -n $BASE_REF ]] || usage_die "missing required argument --base"
}

check_tools() {
    if ((BASH_VERSINFO[0] < 4 || (BASH_VERSINFO[0] == 4 && BASH_VERSINFO[1] < 4))); then
        die "bash 4.4 or newer required, found $BASH_VERSION"
    fi
    command -v jq >/dev/null || die "required command not found: jq"
    local version major minor
    version=$(git version)
    version=${version#git version }
    major=${version%%.*}
    minor=${version#*.}
    minor=${minor%%.*}
    [[ $major =~ ^[0-9]+$ && $minor =~ ^[0-9]+$ ]] || die "cannot parse git version $version"
    if ((major < 2 || (major == 2 && minor < 41))); then
        die "git 2.41 or newer required, found $version"
    fi
}

resolve_commit() {
    local sha
    [[ $1 != -* ]] || die "unknown ref $1"
    sha=$(g rev-parse --verify --quiet "$1^{commit}") || die "unknown ref $1"
    printf '%s\n' "$sha"
}

#######################################
# Returns:
#   0 when $1 is an ancestor of or equal to $2, 1 when not; any other git failure exits.
#######################################
is_ancestor() {
    local rc=0
    g merge-base --is-ancestor "$1" "$2" || rc=$?
    ((rc <= 1)) || die "git merge-base --is-ancestor failed with exit $rc for $1 $2"
    return "$rc"
}

merge_base() {
    local rc=0 sha
    sha=$(g merge-base "$1" "$2") || rc=$?
    ((rc != 1)) || die "no merge base between $1 and $2"
    ((rc == 0)) || die "git merge-base failed with exit $rc for $1 $2"
    printf '%s\n' "$sha"
}

#######################################
# Arguments:
#   head, base
# Outputs:
#   the newest two-parent commit on the first-parent chain of head whose second parent is an
#   ancestor of base, or nothing when there is none
#######################################
find_last_merge() {
    local commit p1 p2 extra
    g rev-list --first-parent --min-parents=2 --parents "$1" >"$WORK/merges" || die "git rev-list failed for $1"
    while read -r commit p1 p2 extra; do
        [[ -z $extra ]] || continue
        if is_ancestor "$p2" "$2"; then
            printf '%s\n' "$commit"
            return 0
        fi
    done <"$WORK/merges"
}

#######################################
# Resolves the mode and the range, and lists the range commits and the branch-owned files.
# Globals:
#   MODE HEAD_SHA BASE_SHA MERGE_SHA RANGE_FROM RANGE_TO COMMIT_COUNT (written)
# Outputs:
#   $WORK/range-shas, $WORK/owned-files
#######################################
resolve_scope() {
    local owned_base="" missing
    BASE_SHA=$(resolve_commit "$BASE_REF")
    MERGE_SHA=""
    RANGE_FROM=""
    RANGE_TO=""
    COMMIT_COUNT=0
    if [[ -n $MERGE_REF ]]; then
        MERGE_SHA=$(resolve_commit "$MERGE_REF")
        MODE=merge
        HEAD_SHA=$MERGE_SHA
        [[ -z $HEAD_REF ]] || HEAD_SHA=$(resolve_commit "$HEAD_REF")
    fi
    if [[ -z $MERGE_REF ]]; then
        HEAD_SHA=$(resolve_commit "${HEAD_REF:-HEAD}")
        missing=$(g rev-list --count "$HEAD_SHA..$BASE_SHA") || die "git rev-list failed for $HEAD_SHA..$BASE_SHA"
        MODE=noop
        if ((missing > 0)); then
            MODE=pending
            RANGE_FROM=$(merge_base "$HEAD_SHA" "$BASE_SHA")
            RANGE_TO=$BASE_SHA
            owned_base=$RANGE_FROM
        fi
        if ((missing == 0)); then
            MERGE_SHA=$(find_last_merge "$HEAD_SHA" "$BASE_SHA")
            [[ -z $MERGE_SHA ]] || MODE=last-merge
        fi
    fi

    if [[ -n $MERGE_SHA ]]; then
        local parents p1 p2 extra
        parents=$(g rev-list --parents -n 1 "$MERGE_SHA") || die "git rev-list failed for $MERGE_SHA"
        read -r _ p1 p2 extra <<<"$parents"
        [[ -n ${p2:-} && -z ${extra:-} ]] || die "not a commit with exactly two parents: $MERGE_REF"
        RANGE_FROM=$(merge_base "$p1" "$p2")
        RANGE_TO=$p2
        owned_base=$p2
        is_ancestor "$MERGE_SHA" "$HEAD_SHA" || die "merge commit $MERGE_SHA is not an ancestor of the head $HEAD_SHA"
    fi

    if [[ -n $SINCE_REF && -n $RANGE_TO ]]; then
        local since
        since=$(resolve_commit "$SINCE_REF")
        is_ancestor "$since" "$RANGE_TO" || die "--since $SINCE_REF is not an ancestor of the range end $RANGE_TO"
        RANGE_FROM=$since
    fi

    : >"$WORK/range-shas"
    : >"$WORK/owned-files"
    [[ $MODE != noop ]] || return 0
    COMMIT_COUNT=$(g rev-list --count --no-merges "$RANGE_FROM..$RANGE_TO") || die "git rev-list failed for $RANGE_FROM..$RANGE_TO"
    if ((COMMIT_COUNT == 0)); then
        MODE=noop
        return 0
    fi
    g rev-list --no-merges "$RANGE_FROM..$RANGE_TO" >"$WORK/range-shas" || die "git rev-list failed for $RANGE_FROM..$RANGE_TO"

    # Owned: every file whose content at the head differs from the base the branch last merged, or that the base lacks.
    g diff --name-only --no-renames --diff-filter=AM "$owned_base" "$HEAD_SHA" -- \
        | sort -u >"$WORK/owned-files" || die "cannot list the files the branch owns at $HEAD_SHA"
}

#######################################
# Globals:
#   TRIAL_MERGE (written)
# Outputs:
#   $WORK/conflicts
#######################################
trial_merge() {
    TRIAL_MERGE=skipped
    : >"$WORK/conflicts"
    [[ $MODE == pending ]] || return 0
    local rc=0
    g merge-tree --write-tree --name-only --no-messages "$HEAD_SHA" "$RANGE_TO" >"$WORK/merge-tree" || rc=$?
    ((rc <= 1)) || die "git merge-tree failed with exit $rc for $HEAD_SHA $RANGE_TO"
    TRIAL_MERGE=clean
    ((rc == 0)) || TRIAL_MERGE=conflicts
    tail -n +2 "$WORK/merge-tree" | awk 'NF > 0' | sort -u >"$WORK/conflicts"
}

#######################################
# Outputs:
#   $WORK/items (id class commit path detail rank commits), $WORK/tokens (id token sites),
#   $WORK/generated (id path), $WORK/commit-files (commit path)
#######################################
harvest() {
    : >"$WORK/items"
    : >"$WORK/tokens"
    : >"$WORK/generated"
    : >"$WORK/commit-files"
    [[ $MODE != noop ]] || return 0

    g log --no-walk=unsorted --stdin --name-status -M --no-color --format='%x01commit %H' <"$WORK/range-shas" >"$WORK/name-status" \
        || die "git log --name-status failed for $RANGE_FROM..$RANGE_TO"
    awk -v classes_file="$SCRIPT_DIR/path-classes.tsv" -v gates_file="$SCRIPT_DIR/gates.tsv" -f "$SCRIPT_DIR/classify.awk" \
        "$SCRIPT_DIR/path-classes.tsv" "$SCRIPT_DIR/gates.tsv" "$WORK/name-status" >"$WORK/classified" \
        || die "cannot classify the paths changed in $RANGE_FROM..$RANGE_TO"
    awk -F '\t' '$1 == "F" { print $2 "\t" $4 }' "$WORK/classified" | sort -u >"$WORK/commit-files"
    awk -F '\t' '$1 == "R"' "$WORK/classified" >"$WORK/targets"
    awk -F '\t' '$1 == "I"' "$WORK/classified" >"$WORK/item-rows"

    if [[ -s $WORK/targets ]]; then
        # The patch stream keeps range order so each commit is processed as one block.
        awk -F '\t' 'NR == FNR { want[$2] = 1; next } $0 in want' "$WORK/targets" "$WORK/range-shas" >"$WORK/patch-shas"
        g log --no-walk=unsorted --stdin -p -U0 -M --diff-algorithm=myers --no-ext-diff --no-textconv --no-color \
            --src-prefix=a/ --dst-prefix=b/ --submodule=short --format='%x01commit %H' <"$WORK/patch-shas" \
            | awk -v targets_file="$WORK/targets" -v stop_file="$SCRIPT_DIR/stop-tokens.txt" \
                -f "$SCRIPT_DIR/words.awk" -f "$SCRIPT_DIR/patches.awk" "$WORK/targets" "$SCRIPT_DIR/stop-tokens.txt" - \
                >"$WORK/patch-rows" || die "cannot read the patches of $RANGE_FROM..$RANGE_TO"
        awk -F '\t' '$1 == "I"' "$WORK/patch-rows" >>"$WORK/item-rows"
        # Keep the 10 tokens with the most sites per item, ties in byte order.
        awk -F '\t' 'BEGIN { OFS = "\t" } $1 == "K" { print $2, $3, $4 }' "$WORK/patch-rows" \
            | sort -t $'\t' -k1,1 -k3,3nr -k2,2 \
            | awk -F '\t' '$1 != last { last = $1; n = 0 } ++n <= 10' >"$WORK/tokens"
    fi

    dependency_items >>"$WORK/item-rows"

    # A generated-artifact item needs a range change to a matching output and an owned matching output.
    awk -F '\t' '
        FILENAME == ARGV[1] { if (FNR > 1) { na++; a_id[na] = $1; a_re[na] = $2; a_cmd[na] = $3 } next }
        FILENAME == ARGV[2] { owned[++no] = $0; next }
        $1 == "F" {
            for (i = 1; i <= na; i++) {
                if ($4 !~ a_re[i] || (i SUBSEP $2) in seen) continue
                seen[i, $2] = 1
                if (i in a_commit) { a_commits[i] = a_commits[i] "," $2; continue }
                a_commit[i] = $2
                a_commits[i] = $2
            }
        }
        END {
            for (i = 1; i <= na; i++) {
                if (!(i in a_commit)) continue
                id = "generated-artifact:" substr(a_commit[i], 1, 12) ":" a_id[i]
                count = 0
                for (k = 1; k <= no; k++) if (owned[k] ~ a_re[i]) { count++; print "G\t" id "\t" owned[k] }
                if (count > 0) print "I\t" id "\tgenerated-artifact\t" a_commit[i] "\t" a_id[i] "\t" a_cmd[i] "\t" count "\t" a_commits[i]
            }
        }
    ' "$SCRIPT_DIR/generated-artifacts.tsv" "$WORK/owned-files" "$WORK/classified" >"$WORK/generated-rows" \
        || die "cannot match the generated artifacts of $RANGE_FROM..$RANGE_TO"
    awk -F '\t' '$1 == "I"' "$WORK/generated-rows" >>"$WORK/item-rows"
    awk -F '\t' 'BEGIN { OFS = "\t" } $1 == "G" { print $2, $3 }' "$WORK/generated-rows" >"$WORK/generated"
    awk -F '\t' 'BEGIN { OFS = "\t" } { print $2, $3, $4, $5, $6, $7, $8 }' "$WORK/item-rows" >"$WORK/items"
}

# Prints the file $2 at commit $1, or nothing when the commit lacks it.
file_at() {
    local listed
    listed=$(g --literal-pathspecs ls-tree "$1" -- "$2") || die "git ls-tree failed for $1 $2"
    [[ -n $listed ]] || return 0
    g show "$1:$2" || die "git show failed for $1:$2"
}

#######################################
# One item per package whose constraint differs between RANGE_FROM and RANGE_TO in a manifest the
# range changed; only the requirement sections count, a missing side reads "(none)".
# Outputs:
#   item rows (I id class commit path detail rank commits), commit being the newest commit that changed
#   the manifest and commits all of them, newest first
#######################################
dependency_items() {
    local commit path commits
    awk -F '\t' '
        $1 == "D" && !($3 in commits) { order[++n] = $3; first[$3] = $2; commits[$3] = $2; next }
        $1 == "D" { commits[$3] = commits[$3] "," $2 }
        END { for (i = 1; i <= n; i++) print first[order[i]] "\t" order[i] "\t" commits[order[i]] }
    ' "$WORK/classified" >"$WORK/manifests"
    while IFS=$'\t' read -r commit path commits; do
        file_at "$RANGE_FROM" "$path" >"$WORK/manifest-old"
        file_at "$RANGE_TO" "$path" >"$WORK/manifest-new"
        jq -nr --rawfile old "$WORK/manifest-old" --rawfile new "$WORK/manifest-new" --arg commit "$commit" --arg path "$path" \
            --arg commits "$commits" '
            def requirements: (if . == "" then {} else fromjson end) as $doc
                | [("require", "require-dev", "dependencies", "devDependencies", "peerDependencies") as $section
                    | ($doc[$section] // {}) | to_entries[] | {key: ($section + ":" + .key), value: {name: .key, constraint: (.value | tostring)}}]
                | from_entries;
            ($old | requirements) as $o | ($new | requirements) as $n
            | (($o + $n) | keys[]) as $key
            | ($o[$key].constraint // "(none)") as $before | ($n[$key].constraint // "(none)") as $after
            | select($before != $after)
            | ["I", "dependency:\($commit[:12]):\($path):\($key)", "dependency", $commit, $path,
               "\(($o[$key] // $n[$key]).name) \($before) -> \($after)", "0", $commits] | @tsv
        ' || die "cannot compare the requirements of $path between $RANGE_FROM and $RANGE_TO"
    done <"$WORK/manifests"
}

#######################################
# Runs git grep over the owned files in batches that stay well below ARG_MAX.
# Arguments:
#   pattern file
# Outputs:
#   git grep lines, NUL separated as git grep --null writes them
#######################################
grep_owned() {
    local limit size=0 path rc
    limit=$(($(getconf ARG_MAX) / 4))
    local -a batch=()
    while IFS= read -r path; do
        batch+=(":(literal)$path")
        size=$((size + ${#path} + 26))
        ((size > limit)) || continue
        rc=0
        g_grep "$HEAD_SHA" --null -n -I -w -F -f "$1" "$HEAD_SHA" -- "${batch[@]}" "${SEARCH_EXCLUDES[@]}" || rc=$?
        ((rc <= 1)) || die "git grep failed with exit $rc at $HEAD_SHA"
        batch=()
        size=0
    done <"$WORK/owned-files"
    ((${#batch[@]} > 0)) || return 0
    rc=0
    g_grep "$HEAD_SHA" --null -n -I -w -F -f "$1" "$HEAD_SHA" -- "${batch[@]}" "${SEARCH_EXCLUDES[@]}" || rc=$?
    ((rc <= 1)) || die "git grep failed with exit $rc at $HEAD_SHA"
}

#######################################
# Arguments:
#   tree, output file
# Outputs:
#   per token of $WORK/token-list the number of files at tree that contain it as a whole word
#######################################
count_token_files() {
    local rc=0
    # Both base counts read gitattributes from the range end, so a file the range marks generated is excluded from both.
    g_grep "$RANGE_TO" --null -I -w -F -f "$WORK/token-list" "$1" -- "${SEARCH_EXCLUDES[@]}" >"$WORK/tree-grep" || rc=$?
    ((rc <= 1)) || die "git grep failed with exit $rc at $1"
    tr '\000' '\t' <"$WORK/tree-grep" | awk -F '\t' -v prefix="$1:" '
        FILENAME == ARGV[1] { want[$0] = 1; count[$0] = 0; next }
        {
            path = substr($1, length(prefix) + 1)
            m = split(substr($0, length($1) + 2), runs, /[^A-Za-z0-9_]+/)
            for (j = 1; j <= m; j++) if ((runs[j] in want) && !((runs[j], path) in seen)) { seen[runs[j], path] = 1; count[runs[j]]++ }
        }
        END { for (t in count) print t "\t" count[t] }
    ' "$WORK/token-list" - >"$2" || die "cannot count the token files at $1"
}

#######################################
# Outputs:
#   $WORK/candidates (id token path line text),
#   $WORK/controls (id token base_before_hits base_after_hits owned_hits owned_symbol_hits)
#######################################
candidates() {
    : >"$WORK/candidates"
    : >"$WORK/controls"
    [[ -s $WORK/tokens ]] || return 0

    awk -F '\t' '{ print $2 }' "$WORK/tokens" | sort -u >"$WORK/token-list"
    count_token_files "$RANGE_FROM" "$WORK/before-hits"
    count_token_files "$RANGE_TO" "$WORK/after-hits"
    # A before token stays only when the base moved away from it: fewer base files hold it at the range end than at the start.
    awk -F '\t' '
        FILENAME == ARGV[1] { before[$1] = $2; next }
        FILENAME == ARGV[2] { after[$1] = $2; next }
        (after[$2] + 0) < (before[$2] + 0)
    ' "$WORK/before-hits" "$WORK/after-hits" "$WORK/tokens" >"$WORK/kept-tokens"
    mv "$WORK/kept-tokens" "$WORK/tokens"
    [[ -s $WORK/tokens ]] || return 0

    awk -F '\t' '{ print $2 }' "$WORK/tokens" | sort -u >"$WORK/kept-token-list"
    awk -F '\t' 'NR == FNR { want[$1] = 1; next } $1 in want { print $5 }' "$WORK/tokens" "$WORK/items" \
        | sort -u - "$WORK/kept-token-list" | awk 'NF > 0' >"$WORK/patterns"

    : >"$WORK/owned-rows"
    if [[ -s $WORK/owned-files ]]; then
        grep_owned "$WORK/patterns" | tr '\000' '\t' \
            | awk -v prefix="$HEAD_SHA:" -v tokens_file="$WORK/tokens" -v items_file="$WORK/items" -v commit_files_file="$WORK/commit-files" \
                -f "$SCRIPT_DIR/words.awk" -f "$SCRIPT_DIR/owned-lines.awk" \
                "$WORK/tokens" "$WORK/items" "$WORK/commit-files" - >"$WORK/owned-rows" \
            || die "cannot search the owned files at $HEAD_SHA"
    fi

    awk -F '\t' 'BEGIN { OFS = "\t" } $1 == "C" { print $2, $3, $4, $5, $6 }' "$WORK/owned-rows" >"$WORK/candidates"
    awk -F '\t' '
        FILENAME == ARGV[1] { before[$1] = $2; next }
        FILENAME == ARGV[2] { after[$1] = $2; next }
        FILENAME == ARGV[3] { if ($1 == "C") hits[$2, $3]++; if ($1 == "Y") sym[$2] = $3; next }
        { print $1 "\t" $2 "\t" (before[$2] + 0) "\t" (after[$2] + 0) "\t" (hits[$1, $2] + 0) "\t" (sym[$1] + 0) }
    ' "$WORK/before-hits" "$WORK/after-hits" "$WORK/owned-rows" "$WORK/tokens" >"$WORK/controls"
}

assemble() {
    MODE=$MODE HEAD_SHA=$HEAD_SHA BASE_SHA=$BASE_SHA MERGE_SHA=$MERGE_SHA RANGE_FROM=$RANGE_FROM \
        RANGE_TO=$RANGE_TO COMMIT_COUNT=$COMMIT_COUNT TRIAL_MERGE=$TRIAL_MERGE \
        jq -n \
        --rawfile items "$WORK/items" \
        --rawfile tokens "$WORK/tokens" \
        --rawfile controls "$WORK/controls" \
        --rawfile candidates "$WORK/candidates" \
        --rawfile generated "$WORK/generated" \
        --rawfile conflicts "$WORK/conflicts" \
        --rawfile owned "$WORK/owned-files" \
        --rawfile gates "$SCRIPT_DIR/gates.tsv" \
        -f "$SCRIPT_DIR/assemble.jq" >"$OUT_DIR/base-adoption-input.json" \
        || die "cannot assemble $OUT_DIR/base-adoption-input.json"
}

main() {
    parse_args "$@"
    check_tools
    g rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "not inside a git work tree: $PWD"
    if [[ -z $OUT_DIR ]]; then OUT_DIR=$(mktemp -d); fi
    mkdir -p "$OUT_DIR"
    OUT_DIR=$(cd "$OUT_DIR" && pwd -P)
    cd "$(g rev-parse --show-toplevel)"
    WORK=$(mktemp -d)
    # shellcheck disable=SC2064 # WORK is fixed from here on, so expanding it now is intended
    trap "rm -rf '$WORK'" EXIT
    load_search_excludes

    resolve_scope
    trial_merge
    harvest
    candidates
    assemble
    printf '%s\n' "$OUT_DIR/base-adoption-input.json"
}

# A direct run exits here, so bash never parses the Bats-only @test syntax below.
if [[ ${BASH_SOURCE[0]} == "$0" ]]; then
    set -euo pipefail
    export LC_ALL=C
    SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)
    main "$@"
    exit
fi

# Bats sources a preprocessed copy from a temporary directory; the data files sit beside the test file.
SCRIPT_DIR=$BATS_TEST_DIRNAME

bats_require_minimum_version 1.5.0

# Creates a throwaway repository with the branch trunk and enters it; remove_repo deletes it.
new_repo() {
    REPO=$(mktemp -d)
    git -C "$REPO" init -q -b trunk
    git -C "$REPO" config user.name test
    git -C "$REPO" config user.email test@example.com
    git -C "$REPO" config commit.gpgsign false
    cd "$REPO" || return 1
}

remove_repo() {
    rm -rf "$REPO"
}

setup() {
    load ../tests/helpers/put
    load ../tests/helpers/commit-all
    load ../tests/helpers/call-sites
    load ../tests/helpers/story
    load ../tests/helpers/run-json
    new_repo
}

teardown() {
    remove_repo
}

@test "pending, merge and last-merge modes give the same range for the same merge" {
    build_story
    local base_tip
    base_tip=$(git rev-parse trunk)

    run_json --base trunk --head feature
    [ "$(q .scope.mode)" = pending ]
    [ "$(q .scope.range_from)" = "$FORK_POINT" ]
    [ "$(q .scope.range_to)" = "$base_tip" ]
    [ "$(q .scope.commit_count)" = 7 ]

    git merge -q --no-edit trunk
    local merge
    merge=$(git rev-parse HEAD)

    run_json --base trunk --merge "$merge"
    [ "$(q .scope.mode)" = merge ]
    [ "$(q '[.scope.range_from, .scope.range_to, .scope.commit_count] | join(" ")')" = "$FORK_POINT $base_tip 7" ]

    run_json --base trunk
    [ "$(q .scope.mode)" = last-merge ]
    [ "$(q .scope.merge)" = "$merge" ]
    [ "$(q '[.scope.range_from, .scope.range_to, .scope.commit_count] | join(" ")')" = "$FORK_POINT $base_tip 7" ]
}

@test "noop when the base has nothing new and the branch never merged it" {
    put README.md 'readme'
    commit_all init
    git checkout -q -b feature
    put docs/notes.txt 'branch notes'
    commit_all 'branch: notes'

    run_json --base trunk
    [ "$(q .scope.mode)" = noop ]
    [ "$(q '[.items, .conflicts, .owned_files, .gate_plan] | map(length) | add')" = 0 ]
}

@test "owned files are the branch's own files, not files that arrived through an earlier base merge" {
    build_story

    run_json --base trunk --head feature
    [ "$(q '.owned_files | join(",")')" = "docs/notes.txt,src/Administration/Resources/app/administration/public-api/module/sw-branch/sw-b.api.md,src/Core/Bar/BranchUser.php,src/Core/Bar/Keeps.php,src/Core/Bar/Thingy.php,src/Core/Bar/deps.lock,src/Core/Foo/CallA.php" ]
}

@test "a file created inside a merge commit on the branch is owned" {
    build_story
    git merge -q --no-commit trunk
    put src/Core/Bar/MergeMade.php '<?php class MergeMade {}'
    git add -A
    git commit -q --no-edit

    run_json --base trunk
    [ "$(q '.owned_files | index(["src/Core/Bar/MergeMade.php"]) != null')" = true ]
}

@test "trial merge reports the path both sides changed" {
    build_story
    put phpstan.neon.dist 'parameters:
    level: 7'
    commit_all 'branch: lower phpstan level'

    run_json --base trunk --head feature
    [ "$(q .scope.trial_merge)" = conflicts ]
    [ "$(q '.conflicts | join(",")')" = phpstan.neon.dist ]
}

@test "harvest writes one item per class and none for an unclassified path" {
    build_story

    run_json --base trunk --head feature
    [ "$(q '[.items[].class] | unique | join(",")')" = "bc-convention,dependency,gate,generated-artifact,guideline,migration-tooling,reusable-surface" ]
    [ "$(q '[.items[] | select(.path == "README.md")] | length')" = 0 ]
    [ "$(q '.items[] | select(.class == "gate") | .detail')" = phpstan ]
}

@test "a path two base commits changed yields one item listing both commits, newest first" {
    build_story
    local expected
    expected=$(git log --format=%H "$FORK_POINT..trunk" -- phpstan.neon.dist | paste -s -d , -)

    run_json --base trunk --head feature
    [ "$(q '[.items[] | select(.path == "phpstan.neon.dist")] | length')" = 1 ]
    [ "$(q '.items[] | select(.path == "phpstan.neon.dist") | .commits | join(",")')" = "$expected" ]
    [ "$(q '.items[] | select(.path == "phpstan.neon.dist") | .commit')" = "${expected%%,*}" ]
}

@test "a token the base still uses in as many files after the range is no before token and yields no candidate" {
    build_story

    run_json --base trunk --head feature
    [ "$(q '[.items[].before_tokens[] | select(.token == "StillUsed")] | length')" = 0 ]
    [ "$(q '[.items[].candidates[] | select(.path == "src/Core/Bar/Keeps.php")] | length')" = 0 ]
}

@test "a base file the range marks linguist-generated does not make its token look declined" {
    put src/Core/Foo/OldThing.php '<?php class OldThing {}'
    call_site CallA
    call_site CallB
    put lib/Extra1.php 'StillUsed::keep();'
    put lib/Extra2.php 'StillUsed::keep();'
    put lib/Extra3.php 'StillUsed::keep();'
    commit_all init
    git branch feature
    put src/Core/Foo/NewHelper.php '<?php class NewHelper {}'
    migrated_call_site CallA
    migrated_call_site CallB
    put lib/KeepA.php 'StillUsed::keep();'
    put lib/KeepB.php 'StillUsed::keep();'
    put .gitattributes 'lib/Extra*.php linguist-generated=true'
    commit_all 'base: add NewHelper, migrate call sites, mark Extra generated'
    git checkout -q feature
    put src/Core/Bar/Keeps.php 'StillUsed::keep();'
    commit_all 'branch: keeps'

    run_json --base trunk --head feature
    [ "$(q '[.items[].before_tokens[] | select(.token == "StillUsed")] | length')" = 0 ]
}

@test "a migration tool is one item for its directory, and fixtures and skill tests yield no item" {
    put README.md 'readme'
    commit_all init
    git branch feature
    local tool=src/Administration/Resources/app/administration/scripts/codemods/new-rule
    put "$tool/index.ts" 'export default 1;'
    put "$tool/lib/transform.ts" 'export const transform = 1;'
    put "$tool/__fixtures__/input.ts" 'export const input = 1;'
    put .agents/skills/some-skill/SKILL.md 'rule'
    put .agents/skills/some-skill/tests/case/AGENTS.md 'fixture'
    put .agents/skills/some-skill/scripts/run.sh 'true'
    commit_all 'base: codemod and skill'
    git checkout -q feature
    put docs/notes.txt 'branch notes'
    commit_all 'branch: notes'

    run_json --base trunk --head feature
    [ "$(q '[.items[] | "\(.class) \(.path)"] | sort | join(",")')" = "guideline .agents/skills/some-skill/SKILL.md,migration-tooling $tool" ]
}

@test "a token whose base file count dropped over the range stays a before token" {
    build_story

    run_json --base trunk --head feature
    [ "$(q '.items[].before_tokens[] | select(.token == "OldThing") | "\(.base_before_hits) \(.base_after_hits)"')" = "3 1" ]
}

@test "candidates skip an owned lock file and an owned file marked linguist-generated" {
    build_story

    run_json --base trunk --head feature
    [ "$(q '[.items[].candidates[] | select(.path | endswith(".lock") or endswith(".api.md"))] | length')" = 0 ]
}

@test "harvest ranks a new helper by its migrated call sites and keeps only removed-only, non-stop tokens" {
    build_story

    run_json --base trunk --head feature
    local item='.items[] | select(.class == "reusable-surface")'
    [ "$(q "$item | .detail")" = NewHelper ]
    [ "$(q "$item | .rank")" = 2 ]
    [ "$(q "[$item | .before_tokens[].token] | index(\"OldThing\") != null")" = true ]
    [ "$(q "[$item | .before_tokens[].token] | index(\"public\") == null")" = true ]
    [ "$(q "[$item | .before_tokens[].token] | index(\"value\") == null")" = true ]
}

@test "harvest describes a dependency bump as name old -> new" {
    build_story

    run_json --base trunk --head feature
    [ "$(q '.items[] | select(.class == "dependency") | .detail')" = 'twig/twig ^3.29.0 -> ^3.31.1' ]
}

@test "a changed composer script produces no dependency item" {
    build_story

    run_json --base trunk --head feature
    [ "$(q '[.items[] | select(.class == "dependency" and (.detail | startswith("lint ")))] | length')" = 0 ]
}

@test "generated-artifact item lists the snapshot outputs the branch owns" {
    build_story

    run_json --base trunk --head feature
    [ "$(q '.items[] | select(.class == "generated-artifact") | .generated_owned | join(",")')" = src/Administration/Resources/app/administration/public-api/module/sw-branch/sw-b.api.md ]
}

@test "generated-artifact item is absent when the branch owns no matching output" {
    build_story without-snapshot

    run_json --base trunk --head feature
    [ "$(q '[.items[] | select(.class == "generated-artifact")] | length')" = 0 ]
}

@test "candidates hold the branch's whole-word use of a before token with path and line" {
    build_story

    run_json --base trunk --head feature
    [ "$(q '.items[].candidates[] | select(.token == "OldThing") | "\(.path):\(.line)"')" = src/Core/Bar/BranchUser.php:6 ]
}

@test "candidates skip a longer identifier and a file the base commit migrated itself" {
    build_story

    run_json --base trunk --head feature
    [ "$(q '[.items[].candidates[] | select(.path == "src/Core/Bar/Thingy.php" or .path == "src/Core/Foo/CallA.php")] | length')" = 0 ]
    [ "$(q '.items[].before_tokens[] | select(.token == "OldThing") | .owned_hits')" = 1 ]
}

@test "two runs over the same refs give byte-identical JSON" {
    build_story

    run_json --base trunk --head feature
    local first=$JSON
    run_json --base trunk --head feature
    cmp "$first" "$JSON"
}

@test "an unknown ref exits non-zero and names the ref" {
    build_story

    run --separate-stderr "$RUN_SH" --base no-such-ref --out "$BATS_TEST_TMPDIR/out"
    [ "$status" -eq 1 ]
    [[ $stderr == *no-such-ref* ]]
}
