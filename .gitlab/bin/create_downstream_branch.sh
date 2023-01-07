#!/usr/bin/env sh
set -eu

CI_API_V4_URL="${CI_API_V4_URL}"
CI_GITLAB_API_TOKEN="${CI_GITLAB_API_TOKEN}"

VERSION_REGEX='^(6)\.([0-9]+)\.([0-9]+)\.([0-9]+)$'

major_version() {
    echo "${1}" | sed -E "s/${VERSION_REGEX}/\2/"
}

minor_version() {
    echo "${1}" | sed -E "s/${VERSION_REGEX}/\3/"
}

patch_version() {
    echo "${1}" | sed -E "s/${VERSION_REGEX}/\4/"
}

previous_patch_version() {
    FULL_VERSION="${1}"
    PATCH_VERSION=$(patch_version "${FULL_VERSION}")

    if [ "${PATCH_VERSION}" -gt "0" ]; then
        PATCH_VERSION=$(( PATCH_VERSION - 1 ))
    fi

    printf '6.%s.%s.%s' \
        $(major_version "${FULL_VERSION}") \
        $(minor_version "${FULL_VERSION}") \
        ${PATCH_VERSION}
}

get_branch() {
    PROJECT_PATH="${1}"
    BRANCH="${2}"

    curl -sSI -X GET -H "Private-Token: ${CI_GITLAB_API_TOKEN}" \
        "${CI_API_V4_URL}/projects/${PROJECT_PATH}/repository/branches?search=${BRANCH}"
}

get_branch_count() {
    get_branch "$@" | grep -i "x-total:" | sed -E "s/^(x-total: )([0-9]+)(.*)$/\2/"
}

create_branch() {
    PROJECT_PATH="${1}" # Path of the project, the branch should be created in. URL-escaped.
    BRANCH="${2}" # Name of the branch that should be created.
    PARENT_BRANCH="${3}" # Name of the branch, the new one should be split off of. Provide the default branch here, it will be replaced by the previous patch version if necessary.

    if [ $(get_branch_count "${PROJECT_PATH}" "${BRANCH}") -lt "1" ]; then
        # If PATCH_RELEASE is > 0, set PARENT_BRANCH to the previous release branch.
        if [ $(patch_version "${BRANCH}") -gt "0" ]; then
            PARENT_BRANCH=$(previous_patch_version "${BRANCH}")
        fi

        curl -sS -X POST -H "Private-Token: ${CI_GITLAB_API_TOKEN}" \
                --form "branch=${BRANCH}" \
                --form "ref=${PARENT_BRANCH}" \
                "${CI_API_V4_URL}/projects/${PROJECT_PATH}/repository/branches"
    fi
}

create_branch "$@"
