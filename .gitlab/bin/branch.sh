#!/usr/bin/env sh
set -eu

if [ -n "${DEBUG:-}" ]; then
    set -x
fi

CI_API_V4_URL="${CI_API_V4_URL}"
CI_GITLAB_API_TOKEN="${CI_GITLAB_API_TOKEN}"

get_branch_info() {
    local project_path="${1}"
    local branch="${2}"

    if [ "${branch}" = "" ]; then
        return 1
    fi

    curl -sSfI -X GET -H "Private-Token: ${CI_GITLAB_API_TOKEN}" \
        "${CI_API_V4_URL}/projects/${project_path}/repository/branches?search=${branch}"
}

get_branch_count() {
    get_branch_info "$@" | grep -i "x-total:" | sed -E "s/^(x-total: )([0-9]+)(.*)$/\2/"
}

get_latest_succeeded_pipeline_info() {
    local project_path="${1}"
    local branch="${2}"

    if [ "${branch}" = "" ]; then
        return 1
    fi

    curl -sSf -X GET -H "Private-Token: ${CI_GITLAB_API_TOKEN}" -H "Content-Type: text/plain" \
        "${CI_API_V4_URL}/projects/${project_path}/pipelines?ref=${branch}&scope=finished&status=success&order_by=updated_at&sort=desc&per_page=1"
}

get_latest_succeeded_pipeline_sha() {
    local latest_succeeded_pipeline_info=$(get_latest_succeeded_pipeline_info "$@")

    if [ "${latest_succeeded_pipeline_info}" != '[]' ]; then
        printf '%s' "${latest_succeeded_pipeline_info}" | sed -E 's/^(.*"sha":")([[:alnum:]]{40})(.*)$/\2/' # Extract SHA from the response without `jq` available.
    fi
}

create_branch() {
    local project_path="${1}" # Path of the project, the branch should be created in. URL-escaped.
    local branch="${2}" # Name of the branch that should be created.
    local branch_ref="${3}" # Name of the branch/ref the new branch should be created from. Defaults to 'trunk'.

    if [ "${branch}" = "" ] || [ "${branch_ref}" = "" ]; then
        return 1
    fi

    if [ $(get_branch_count "$@") -lt "1" ]; then
        curl -sSf -X POST -H "Private-Token: ${CI_GITLAB_API_TOKEN}" \
            --form "branch=${branch}" \
            --form "ref=${branch_ref}" \
            "${CI_API_V4_URL}/projects/${project_path}/repository/branches"
    fi
}

create_deployment_branch() {
    local project_path="${1}" # Path of the project, the branch should be created in. URL-escaped.
    local deployment_branch_name="${2}"
    local latest_succeeded_pipeline_sha="$(get_latest_succeeded_pipeline_sha ${project_path} 'trunk')"

    create_branch "${project_path}" "${deployment_branch_name}" "${latest_succeeded_pipeline_sha}"
}

"$@"
