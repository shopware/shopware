#!/usr/bin/env sh
set -eu

if [ -n "${DEBUG:-}" ]; then
    set -x
fi

# When running in an alpine container, we need to install GNU sed if it's not
# already available.
#
# Can be removed once https://gitlab.shopware.com/infrastructure/docker-base/-/merge_requests/8 is merged.
if sed --version | grep 'This is not GNU sed' > /dev/null ; then
  apk add sed
fi

DRY_RUN="${DRY_RUN:-}"
TRACE="${TRACE:-}"

TASK="${1}"
PLATFORM_TAG="${2}"
PLATFORM_DIR="${CI_PROJECT_DIR:-$(pwd)}"
GITHUB_SYNC_TOKEN="${GITHUB_SYNC_TOKEN}"
REPOSITORY_API_URL='https://api.github.com/repos/shopware/shopware'

print_usage() {
    echo 'Usage:'
    echo "${0} draft [VERSION]"
    echo "${0} publish [VERSION]"
    exit 1
}

draft_release_payload() {
  local platform_tag; export platform_tag="${1}"
  local platform_version; export platform_version="${platform_tag#v*}"
  local platform_major; export platform_major=$(echo "${platform_version}" | cut -d. -f1,2)
  local release_message_header; export release_message_header="See the [UPGRADE.md](./UPGRADE-${platform_major}.md) for all important technical changes."
  local changelog_entries; export changelog_entries=$(sed -n "/## ${platform_version}/, /##/{ /## ${platform_version}/! { /##/! p } }" "${PLATFORM_DIR}/CHANGELOG.md")

  jq -nc '{
    tag_name: env.platform_tag,
    name: "Release \(env.platform_tag)",
    body: ([env.release_message_header, env.changelog_entries] | join("\n\n")),
    draft: true,
    prerelease: false,
    generate_release_notes: false
  }'
}

publish_release_payload() {
  jq -nc '{draft: false}'
}

request_github() {
  local method; method="${1}"
  local url; url="${2}"
  local data; data="${3:-}"
  local curl_opts; curl_opts="-sSf"

  if [ -n "${TRACE:-}" ]; then
    curl_opts="${curl_opts} --trace-ascii -"
  fi

  if [ -n "${DRY_RUN:-}" ] && [ "${method}" != "GET" ]; then
    xargs -0 printf 'curl %s --request %s --url %s --header %s --header %s --header %s --data %s\n' "${curl_opts}" "${method}" "${url}" 'Accept: application/vnd.github+json' "Authorization: Bearer ${GITHUB_SYNC_TOKEN}" 'X-GitHub-Api-Version: 2022-11-28'
    return
  fi

  curl ${curl_opts} --request "${method}" \
    --url "${url}" \
    --header 'Accept: application/vnd.github+json' \
    --header "Authorization: Bearer ${GITHUB_SYNC_TOKEN}" \
    --header 'X-GitHub-Api-Version: 2022-11-28' \
    --data "${data}"
}

fetch_github_releases() {
  request_github 'GET' "${REPOSITORY_API_URL}/releases"
}

# This unfortunately does not work with draft releases created via API,
# since they're not associated with a tag, even when one is specified.
# https://github.com/mislav/hub/issues/1817
#
# Instead we're using fetch_github_releases and filter_releases_by_tag.
fetch_github_release() {
  local github_release_tag; github_release_tag="${1}"

  request_github 'GET' "${REPOSITORY_API_URL}/releases/tags/${github_release_tag}"
}

post_github_release() {
  request_github 'POST' "${REPOSITORY_API_URL}/releases" "@-"
}

patch_github_release() {
  local github_release_id; github_release_id="${1}"

  request_github 'PATCH' "${REPOSITORY_API_URL}/releases/${github_release_id}" "@-"
}

filter_releases_by_tag() {
  local github_release_tag; export github_release_tag="${1}"

  jq '.[] | select(.tag_name == env.github_release_tag)'
}

get_github_release_id() {
  jq -r '.id'
}

case "${TASK}" in
    draft)
        draft_release_payload "${PLATFORM_TAG}" | post_github_release
        ;;
    publish)
        GITHUB_RELEASE_ID=$(fetch_github_releases | filter_releases_by_tag "${PLATFORM_TAG}" | get_github_release_id)

        if [ -z "${GITHUB_RELEASE_ID}" ]; then
            echo "No release found for tag ${PLATFORM_TAG}"
            exit 1
        fi

        publish_release_payload | patch_github_release "${GITHUB_RELEASE_ID}"
        ;;
    *)
        print_usage
        ;;
esac
