#!/usr/bin/env bash
set -euo pipefail

# Verifies that every feature heading documented in trunk's RELEASE_INFO
# for the given version prefix is also present in the release branch,
# and that the commit which introduced it on trunk is reachable from HEAD.
#
# Usage: verify_release_content.bash <version-prefix>
# Example: verify_release_content.bash 6.7.11
#
# Exit codes:
#   0 — all entries verified (warnings may still be printed)
#   1 — one or more entries are missing from the release branch

TRUNK_REF="origin/trunk"

extract_headings() {
    local version_prefix="$1"
    local escaped_prefix="${version_prefix//./\\.}"
    local in_section=0

    while IFS= read -r line; do
        if [[ "$line" =~ ^#[[:space:]]+${escaped_prefix}\. ]]; then
            in_section=1
            continue
        fi
        if [[ $in_section -eq 1 && "$line" =~ ^#[[:space:]] ]]; then
            in_section=0
            continue
        fi
        if [[ $in_section -eq 1 && "$line" =~ ^###[[:space:]] ]]; then
            echo "$line"
        fi
    done
}

# Returns the most recent commit on trunk that changed the count of the given
# string in the given file (i.e. the commit that introduced or last re-added it).
find_introducing_commit() {
    local heading="$1"
    local file="$2"
    git log "$TRUNK_REF" --format="%H" --max-count=1 -S "$heading" -- "$file"
}

main() {
    local version_prefix="${1:-}"

    if [[ -z "$version_prefix" ]]; then
        echo "Usage: $0 <version-prefix>"
        echo "Example: $0 6.7.11"
        exit 1
    fi

    local major_minor
    major_minor=$(echo "$version_prefix" | cut -d. -f1-2)
    local release_info_file="RELEASE_INFO-${major_minor}.md"

    if [[ ! -f "$release_info_file" ]]; then
        echo "ERROR: ${release_info_file} not found in working directory."
        exit 1
    fi

    echo "Verifying RELEASE_INFO for ${version_prefix}.*"
    echo "  trunk  : ${TRUNK_REF}"
    echo "  branch : $(git rev-parse --abbrev-ref HEAD)"
    echo "  file   : ${release_info_file}"
    echo ""

    # Trunk is the authoritative source for what is supposed to be in the release.
    # The release branch is what actually shipped (or will ship).
    local trunk_headings branch_headings
    trunk_headings=$(git show "${TRUNK_REF}:${release_info_file}" | extract_headings "$version_prefix")
    branch_headings=$(extract_headings "$version_prefix" < "$release_info_file")

    if [[ -z "$trunk_headings" ]]; then
        echo "No entries found for ${version_prefix}.* in trunk's ${release_info_file} — nothing to verify."
        exit 0
    fi

    local -a missing=()
    local -a warnings=()

    while IFS= read -r heading; do
        [[ -z "$heading" ]] && continue

        local text_present=0 commit_reachable=0
        local introducing_commit short_sha

        # Check 1 — text: is the heading present in the release branch's copy of the file?
        # If it's missing here, the RELEASE_INFO update was never merged or cherry-picked into the branch.
        grep -qF "$heading" <<< "$branch_headings" 2>/dev/null && text_present=1

        # Check 2 — commit: find the trunk commit that introduced this heading via pickaxe search (-S),
        # then verify it is a direct ancestor of the release branch HEAD.
        # This confirms the full PR (docs + code) landed in the branch, not just the text.
        introducing_commit=$(find_introducing_commit "$heading" "$release_info_file")
        short_sha="${introducing_commit:0:8}"

        local docs_only=0
        if [[ -n "$introducing_commit" ]]; then
            git merge-base --is-ancestor "$introducing_commit" HEAD 2>/dev/null && commit_reachable=1

            # Check 3 — docs-only: if the introducing commit only touched RELEASE_INFO and nothing else,
            # it was a standalone documentation update decoupled from the feature code.
            # In that case the commit reachability check above tells us nothing about the actual feature —
            # we can only flag it for manual verification.
            local changed_files
            changed_files=$(git diff-tree --no-commit-id -r --name-only "$introducing_commit")
            if [[ "$changed_files" == "$release_info_file" ]]; then
                docs_only=1
            fi
        fi

        if   [[ $text_present -eq 1 && $commit_reachable -eq 1 && $docs_only -eq 0 ]]; then
            : # confirmed — heading and feature code landed in the same commit and are both reachable
        elif [[ $text_present -eq 0 && $commit_reachable -eq 0 ]]; then
            missing+=("${heading} [${short_sha:-unknown}]")
        elif [[ $text_present -eq 1 && $commit_reachable -eq 0 ]]; then
            # RELEASE_INFO was cherry-picked but the original trunk commit is not a direct ancestor.
            # The feature code may have come along with the cherry-pick, but we cannot confirm automatically.
            warnings+=("${heading} [${short_sha}] RELEASE_INFO present but trunk commit not in branch — verify cherry-pick includes feature code")
        elif [[ $text_present -eq 0 && $commit_reachable -eq 1 ]]; then
            # The feature commit is present but the RELEASE_INFO entry was dropped from the branch.
            warnings+=("${heading} [${short_sha}] code commit present but RELEASE_INFO entry missing from branch")
        elif [[ $docs_only -eq 1 ]]; then
            # The heading was added in a commit that only touched RELEASE_INFO, so we cannot trace
            # the feature code back to a specific commit to check reachability.
            warnings+=("${heading} [${short_sha}] RELEASE_INFO was updated in a docs-only commit — feature code commit is unknown, verify manually")
        fi
    done <<< "$trunk_headings"

    local total
    total=$(grep -c '^' <<< "$trunk_headings" || true)

    if [[ ${#warnings[@]} -gt 0 ]]; then
        echo "WARN: ${#warnings[@]} of ${total} entries need manual verification:"
        echo ""
        for entry in "${warnings[@]}"; do
            echo "  ? ${entry}"
        done
        echo ""
    fi

    if [[ ${#missing[@]} -gt 0 ]]; then
        echo "MISSING: ${#missing[@]} of ${total} entries documented on trunk are absent from this release branch:"
        echo ""
        for entry in "${missing[@]}"; do
            echo "  x ${entry}"
        done
        echo ""
        echo "These features were documented in ${release_info_file} on trunk but have not been merged into this release branch."
        exit 1
    fi

    local ok=$(( total - ${#warnings[@]} ))
    echo "OK: ${ok} of ${total} entries confirmed present. ${#warnings[@]} need manual verification (see above)."
}

main "$@"
