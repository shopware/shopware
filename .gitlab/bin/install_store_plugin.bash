#!/usr/bin/env bash

set -euo pipefail
IFS=$'\n\t'

console() {
  ${PROJECT_DIR}/bin/console "$@"
}

download_store_plugin() {
    local technical_name=$1
    local download_info_url="https://api.shopware.com/swplatform/pluginfiles/${technical_name}?domain=sw6.fk6.test.shopware.in&language=en-GB&shopwareVersion=${PLATFORM_VERSION}"
    local info=$(curl \
        --location --silent \
        --header 'Content-Type: application/json' \
        --header 'Accept: application/vnd.api+json,application/json' \
        ${download_info_url})

    local download_url=$(echo "${info}" | jq -r .location)
    if [[ ${download_url} = 'null' ]]; then
        return 1;
    fi

    local destination
    local download_dir
    local plugin_name

    >&2 echo "Downloading store plugin ${technical_name} from $download_url"
    download_dir="$(download_plugin "${download_url}")"
    plugin_name="$(jq '.extra["shopware-plugin-class"]' < "${download_dir}/composer.json" | sed 's/"//g' | tr '\\' "\n" | tail -n 1)"
    destination="${PROJECT_DIR}/custom/plugins/${plugin_name}"

    mv "$download_dir" "$destination"

    echo "$destination"
}

download_plugin() {
    local url=$1
    local auth=${3:-}

    local tmp_dir=$(mktemp -d)

    if [[ -n ${auth} ]]; then
        curl --location --silent --anyauth --user "${auth}" --output "${tmp_dir}/plugin.zip" "${url}"
    else
        curl --location --silent --output "${tmp_dir}/plugin.zip" "${url}"
    fi

    local old_dir="$PWD"

    cd "${tmp_dir}"
    if ! unzip -qqo plugin.zip; then
      >&2 echo "Failed to unzip zip from $url"
      >&2 cat plugin.zip
      rm plugin.zip
      return 1
    fi

    rm plugin.zip

    if [[ -r composer.json ]]; then
        >&2 echo 'plugin has no subfolder'
        echo ${tmp_dir}
    elif [[ -r "${tmp_dir}/$(ls .)/composer.json" ]]; then
        >&2 echo 'plugin is contained in subfolder'
        echo ${tmp_dir}/$(ls .)
    else
        >&2 echo "composer.json not found for $url"
        rm -Rf ${tmp_dir}
        return 1
    fi
}

activate_plugin() {
    local path=$1

    console plugin:refresh > /dev/null

    local plugin_name="$(jq '.extra["shopware-plugin-class"]' < "${path}/composer.json" | sed 's/"//g' | tr '\\' "\n" | tail -n 1)"

    >&2 echo "Installing '${path}' with name '${plugin_name}'"

    console plugin:install --activate ${plugin_name}
}

PROJECT_DIR="${PROJECT_DIR:-$PWD}"
PLATFORM_VERSION="$(jq -r .version < composer.json)"
# fake version if not set
if [[ "$PLATFORM_VERSION" = "null" ]]; then
    LATEST_TAG="$(git -c 'versionsort.suffix=-' ls-remote --exit-code --refs --sort='version:refname' --tags | tail --lines=1 | cut --delimiter='/' --fields=3)"
    PLATFORM_VERSION="${LATEST_TAG#"v"}"
fi

for technical_name in "$@"
do
    destination="$(download_store_plugin "${technical_name}")"
    activate_plugin "${destination}"
done

APP_ENV=e2e console cache:clear
APP_ENV=prod console cache:clear
