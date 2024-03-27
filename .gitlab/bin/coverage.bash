get() {
    local path=${1}
    curl -sSf -X GET -H "Private-Token: ${CI_GITLAB_API_TOKEN}" -H "Content-Type: text/plain" \
        "${CI_API_V4_URL}/${path}"
}

get_latest_pipeline_id() {
    local branch=${1:-trunk}
    get "projects/1/pipelines?ref=${branch}&source=push&scope=finished&status=success&order_by=updated_at&sort=desc&per_page=1" | jq .[0].id -r
}

get_coverage() {
    local pipeline_id=${1}

    get "projects/1/pipelines/${pipeline_id}" | jq .coverage -r
}

add_discussion() {
    local mr_id=${1}
    local body=${2}

    curl -sSf -X POST -H "Private-Token: ${CI_GITLAB_API_TOKEN}" -H "Content-Type: text/plain" \
        "${CI_API_V4_URL}/projects/1/merge_requests/${mr_id}/discussions" \
        -G --data-urlencode "body=${body}"
}

check_coverage() {
    local mr_iid=${1}
    local pipeline_id=${2}

    local mr_target_branch=$(get projects/1/merge_requests/$mr_iid | jq .target_branch -r)
    local upstream_coverage=$(get_coverage $(get_latest_pipeline_id "${mr_target_branch}"))
    local mr_coverage=$(get_coverage ${pipeline_id})

    if [[ $(jq -n "$mr_coverage >= $upstream_coverage") == "true" ]]; then
        echo 'Coverage is fine'
    else
        body="MR coverage (${mr_coverage}) is lower that upstream coverage (${upstream_coverage}). Please make sure that everything that can be covered is covered."

        echo "${body}"

        add_discussion ${mr_iid} "${body}"

        exit 1
    fi
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    set -o errexit
    set -o pipefail

    "$@"
fi