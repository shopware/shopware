# shellcheck shell=bash
# SC2016: the single-quoted file contents are PHP source whose $ must stay literal.
# SC2154: status, output and stderr are set by the bats run helper.
# shellcheck disable=SC2016,SC2154
# Builds throwaway repositories for the run.sh tests. Paths mirror the shipped data tables.

RUN_SH="$BATS_TEST_DIRNAME/run.sh"

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

# Writes $2 to the file $1, creating its directory.
put() {
    mkdir -p "$(dirname "$1")"
    printf '%s\n' "$2" >"$1"
}

commit_all() {
    git add -A
    git commit -q -m "$1"
}

call_site() {
    put "src/Core/Foo/$1.php" "<?php
class $1
{
    public const LEGACY = 1;
    public function run(\$value)
    {
        StillUsed::keep();
        return OldThing::make(\$value);
    }
}"
}

migrated_call_site() {
    put "src/Core/Foo/$1.php" "<?php
class $1
{
    public function run(\$value)
    {
        return NewHelper::create(\$value);
    }
}"
}

# Writes composer.json with the twig/twig constraint $1 and the lint script $2.
composer_with_twig() {
    put composer.json "{
    \"require\": {
        \"twig/twig\": \"$1\"
    },
    \"scripts\": {
        \"lint\": \"$2\"
    }
}"
}

#######################################
# Base trunk and branch feature: the branch merged an early base commit, then both moved on.
# Snapshot files under public-api/ are marked linguist-generated.
# The base adds NewHelper and migrates CallA and CallB from OldThing::make, moves their StillUsed::keep
# calls into two new files, changes a gate config in two commits, bumps twig/twig and changes a
# composer script, regenerates a snapshot, edits README and adds a guideline, a BC exclusion and a codemod.
# The branch adds BranchUser (still OldThing::make), Thingy (OldThingy only), Keeps (StillUsed::keep),
# a lock file using OldThing::make, edits CallA and, unless $1 is "without-snapshot", owns a snapshot
# file that uses OldThing::make.
# Globals:
#   FORK_POINT (written): the base commit the branch merged before the range
#######################################
build_story() {
    put src/Core/Foo/OldThing.php '<?php
class OldThing { public static function make($value) { return $value; } }'
    call_site CallA
    call_site CallB
    put phpstan.neon.dist 'parameters:
    level: 8'
    composer_with_twig '^3.29.0' 'lint v1'
    put src/Administration/Resources/app/administration/public-api/app/sw-a.api.md 'snapshot v1'
    put README.md 'readme v1'
    put .gitattributes 'src/Administration/Resources/app/administration/public-api/** linguist-generated=true'
    commit_all init
    git branch feature

    put src/Core/Early/EarlyFile.php '<?php class EarlyFile {}'
    commit_all 'base: early file'
    # shellcheck disable=SC2034 # read by the tests
    FORK_POINT=$(git rev-parse HEAD)

    git checkout -q feature
    put docs/notes.txt 'branch notes'
    commit_all 'branch: notes'
    git merge -q --no-edit trunk

    git checkout -q trunk
    put src/Core/Foo/NewHelper.php '<?php
class NewHelper { public static function create($value) { return $value; } }'
    migrated_call_site CallA
    migrated_call_site CallB
    put lib/KeepA.php 'StillUsed::keep();'
    put lib/KeepB.php 'StillUsed::keep();'
    commit_all 'base: add NewHelper and migrate call sites'
    put phpstan.neon.dist 'parameters:
    level: 9'
    commit_all 'base: raise phpstan level'
    composer_with_twig '^3.31.1' 'lint v2'
    commit_all 'base: bump twig'
    put phpstan.neon.dist 'parameters:
    level: 10'
    commit_all 'base: raise phpstan level again'
    put src/Administration/Resources/app/administration/public-api/app/sw-a.api.md 'snapshot v2'
    commit_all 'base: regenerate snapshot'
    put README.md 'readme v2'
    commit_all 'base: readme'
    put adr/2026-01-01-new-rule.md 'decision'
    put .bc-exclude.php '<?php return [];'
    put src/Administration/Resources/app/administration/scripts/codemods/new-rule/index.ts 'export default 1;'
    commit_all 'base: guideline, bc exclusion, codemod'

    git checkout -q feature
    put src/Core/Bar/BranchUser.php '<?php
class BranchUser
{
    public function build($x)
    {
        return OldThing::make($x);
    }
}'
    put src/Core/Bar/Thingy.php '<?php
class Thingy { public function go() { return OldThingy::run(); } }'
    put src/Core/Bar/Keeps.php '<?php
StillUsed::keep();'
    put src/Core/Bar/deps.lock 'OldThing::make()'
    commit_all 'branch: own users'
    {
        printf '%s\n' '// branch comment'
        cat src/Core/Foo/CallA.php
    } >src/Core/Foo/CallA.tmp
    mv src/Core/Foo/CallA.tmp src/Core/Foo/CallA.php
    commit_all 'branch: touch CallA'
    if [[ ${1:-} != without-snapshot ]]; then
        put src/Administration/Resources/app/administration/public-api/module/sw-branch/sw-b.api.md 'OldThing::make() branch snapshot'
        commit_all 'branch: own snapshot'
    fi
}

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
