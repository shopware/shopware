#!/usr/bin/env bash
set -euo pipefail

# Verifies that every feature heading documented in trunk's RELEASE_INFO
# for the given version prefix is also present in the release branch.
#
# Usage: verify_release_content.bash <version-prefix>
# Example: verify_release_content.bash 6.7.11
#
# Compares ### headings inside all matching # <version-prefix>.* sections
# between origin/trunk and the current branch's RELEASE_INFO file.

TRUNK_REF="origin/trunk"

# Reads RELEASE_INFO content from stdin and prints all ### headings found
# inside # <version_prefix>.* sections.
extract_headings() {
    local version_prefix="$1"
    local escaped_prefix="${version_prefix//./\\.}"
    local in_section=0

    while IFS= read -r line; do
        # H1 heading matching the version prefix: # 6.7.11.0 or # 6.7.11.0 (upcoming)
        if [[ "$line" =~ ^#[[:space:]]+${escaped_prefix}\. ]]; then
            in_section=1
            continue
        fi

        # Any other H1 heading ends the current section
        if [[ $in_section -eq 1 && "$line" =~ ^#[[:space:]] ]]; then
            in_section=0
            continue
        fi

        # Collect H3 feature headings within the section
        if [[ $in_section -eq 1 && "$line" =~ ^###[[:space:]] ]]; then
            echo "$line"
        fi
    done
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

    local trunk_headings branch_headings
    trunk_headings=$(git show "${TRUNK_REF}:${release_info_file}" | extract_headings "$version_prefix")
    branch_headings=$(extract_headings "$version_prefix" < "$release_info_file")

    if [[ -z "$trunk_headings" ]]; then
        echo "No entries found for ${version_prefix}.* in trunk's ${release_info_file} — nothing to verify."
        exit 0
    fi

    local -a missing=()
    while IFS= read -r heading; do
        [[ -z "$heading" ]] && continue
        if ! grep -qF "$heading" <<< "$branch_headings" 2>/dev/null; then
            missing+=("$heading")
        fi
    done <<< "$trunk_headings"

    local total
    total=$(grep -c '^' <<< "$trunk_headings" || true)

    if [[ ${#missing[@]} -eq 0 ]]; then
        echo "OK: All ${total} documented entries for ${version_prefix}.* are present in the release branch."
        exit 0
    fi

    echo "MISSING: ${#missing[@]} of ${total} entries documented on trunk are absent from this release branch:"
    echo ""
    for heading in "${missing[@]}"; do
        echo "  ${heading}"
    done
    echo ""
    echo "These features were documented in ${release_info_file} on trunk but have not been merged into this release branch."
    exit 1
}

main "$@"
