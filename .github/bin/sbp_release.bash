#!/usr/bin/env bash
set -eu

if [ -n "${DEBUG:-}" ]; then
    set -x
fi

DRY_RUN="${DRY_RUN:-}"
TRACE="${TRACE:-}"

TASK="${1}"
PLATFORM_TAG="${2}"
PLATFORM_DIR="${CI_PROJECT_DIR:-$(pwd)}"
PLATFORM_VERSION=${PLATFORM_TAG#v*}
PLATFORM_MAJOR_VERSION=$(echo "${PLATFORM_VERSION}" | cut -d '.' -f1,2)
SBP_TOKEN="${SBP_TOKEN}"
SBP_API_URL="${SBP_API_URL:-"${CI_ENVIRONMENT_URL}"}"

SBP_VERSIONS_CACHE_FILE=$(mktemp -p "${PLATFORM_DIR}" --suffix='.json')

if [ ! -f "${SBP_VERSIONS_CACHE_FILE}" ]; then
  touch "${SBP_VERSIONS_CACHE_FILE}"
fi

print_usage() {
  echo "Usage:"
  echo "${0} create [VERSION]"
  echo "${0} publish [VERSION]"
  exit 1
}

create_version_payload() {
  local version_name; export version_name="${PLATFORM_VERSION}"
  local parent_id; export parent_id=$(fetch_sbp_version_id "${PLATFORM_MAJOR_VERSION}")

  jq -nc '{
    name: env.version_name,
    parent: env.parent_id | tonumber,
    status: {
      name: "visible_for_manufacturers"
    }
  }'
}

publish_version_payload() {
  local version_name; export version_name="${PLATFORM_VERSION}"
  local parent_id; export parent_id=$(fetch_sbp_version_id "${PLATFORM_MAJOR_VERSION}")
  local release_date; export release_date="$(date '+%Y-%m-%d')"

  jq -nc '{
    name: env.version_name,
    parent: env.parent_id | tonumber,
    status: {
      name: "public"
    },
    releaseDate: env.release_date
  }'
}

request_sbp() {
  local method; method="${1}"
  local url; url="${2}"
  local data; data="${3:-}"
  local curl_opts; curl_opts="-sSf"

  if [ -n "${TRACE:-}" ]; then
    curl_opts="${curl_opts} --trace-ascii -"
  fi

  if [ -n "${DRY_RUN:-}" ] && [ "${method}" != "GET" ]; then
    xargs -0 printf 'curl %s --request %s --url %s --header %s --header %s --header %s --data %s\n' "${curl_opts}" "${method}" "${url}" 'Accept: application/json' "X-Shopware-Token: [redacted]" 'Content-Type: application/json'
    return
  fi

  curl ${curl_opts} --request "${method}" \
    --url "${url}" \
    --header 'Accept: application/json' \
    --header 'Content-Type: application/json' \
    --header "X-Shopware-Token: ${SBP_TOKEN}" \
    --data "${data}"
}

fetch_sbp_versions() {
  if [ ! -s "${SBP_VERSIONS_CACHE_FILE}" ]; then
    request_sbp 'GET' "${SBP_API_URL}/static/softwareversions" > "${SBP_VERSIONS_CACHE_FILE}"
  fi

  cat "${SBP_VERSIONS_CACHE_FILE}"
}

post_sbp_version() {
  request_sbp 'POST' "${SBP_API_URL}/static/softwareversions" "@-"
}

put_sbp_version() {
  local version_id; version_id=$(fetch_sbp_version_id "${PLATFORM_VERSION}")

  request_sbp 'PUT' "${SBP_API_URL}/static/softwareversions/${version_id}" "@-"
}

filter_versions_by_name() {
  local version_name; export version_name="${1}"

  jq '.[] | select(.name == env.version_name)'
}

get_version_id() {
  jq -r '.id'
}

fetch_sbp_version_id() {
  local version_name; version_name="${1}"

  fetch_sbp_versions | filter_versions_by_name "${version_name}" | get_version_id
}

case "${TASK}" in
  create)
    create_version_payload | post_sbp_version
    ;;
  publish)
    publish_version_payload | put_sbp_version
    ;;
  *)
    print_usage
    ;;
esac
