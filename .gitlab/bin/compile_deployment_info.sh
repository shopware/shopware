#!/usr/bin/env sh
set -eu

if [ -n "${DEBUG:-}" ]; then
    set -x
fi

CI_JOB_TOKEN="${CI_JOB_TOKEN}"
CI_CURRENT_MAJOR_ALIAS="${CI_CURRENT_MAJOR_ALIAS:-}"

# deployment_branch_name returns the branch name for the current deployment.
deployment_branch_name() {
  local from_date="${CI_PIPELINE_CREATED_AT:-now}"

  date --utc --date="${from_date}" +'saas/%Y/%W'
}

# current_major_alias Fetches the latest released version of Shopware 6,
# excluding rc-versions and formats it as a major alias, e.g. `6.6.x-dev`.
#
# Can be overriden by setting the `CI_CURRENT_MAJOR_ALIAS` environment variable.
current_major_alias() {
  if [ -n "${CI_CURRENT_MAJOR_ALIAS}" ]; then
    printf "%s" "${CI_CURRENT_MAJOR_ALIAS}"
    return
  fi

  curl -fsSL "https://releases.shopware.com/changelog/index.json" \
   | jq -r '[.[] | select(test("[a-zA-Z]") | not)] | first | split(".") | [.[0], .[1], "x-dev"] | join(".")'
}

# custom_version_core returns the custom version for the core repositories.
custom_version_core() {
  local branch="$(deployment_branch_name)"
  local major_alias="$(current_major_alias)"

  printf "shopware/platform:dev-%s as %s;shopware/commercial:dev-%s;swag/saas-rufus:dev-%s" "${branch}" "${major_alias}" "${branch}" "${branch}"
}

# custom_version_extensions returns the custom version for the extension
# repositories.
custom_version_extensions() {
  set -eu
  local tmpdir="$(mktemp -d)"

  git clone --depth=1 "https://gitlab-ci-token:${CI_JOB_TOKEN}@gitlab.shopware.com/shopware/6/product/saas.git" "${tmpdir}"
  composer -d "${tmpdir}" show --locked --outdated --direct --format=json > "${tmpdir}/outdated.json"

  jq -r \
    '[.locked[] | select(.name | test("^(shopware|swag)/")) | select(.latest | test("(^dev-|-dev)") | not) | select(."latest-status" | test("update-possible|semver-safe-update")) | .name + ":" + .latest] | join(";")' \
    "${tmpdir}/outdated.json"
}

# deployment_env compiles the environment variables for the deployment.
deployment_env() {
  local update_extensions="${1:-}"

  local deployment_branch_name="$(deployment_branch_name)"
  local custom_version

  if [ -n "${update_extensions}" ]; then
    custom_version="$(custom_version_core);$(custom_version_extensions)"
  else
    custom_version="$(custom_version_core)"
  fi

  cat <<EOF
DEPLOYMENT_BRANCH_NAME=${deployment_branch_name}
CI_UPDATE_DEPENDENCY=1
CUSTOM_VERSION=${custom_version}
GITLAB_MR_TITLE=Deployment - ${deployment_branch_name}
GITLAB_MR_DESCRIPTION_TEXT=This MR has been created automatically to facilitate the deployment \`${deployment_branch_name}\`.
GITLAB_MR_LABELS=workflow::development
GITLAB_MR_ASSIGNEES=shopwarebot
EOF
}

"$@"
