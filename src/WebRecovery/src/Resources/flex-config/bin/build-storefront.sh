#!/usr/bin/env bash

CWD="$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"

set -euo pipefail

export PROJECT_ROOT="${PROJECT_ROOT:-"$(dirname "$CWD")"}"
STOREFRONT_ROOT="${STOREFRONT_ROOT:-"${PROJECT_ROOT}/vendor/shopware/storefront"}"

BIN_TOOL="${CWD}/console"

if [[ ${CI-""} ]]; then
    BIN_TOOL="${CWD}/ci"

    if [[ ! -x "$BIN_TOOL" ]]; then
        chmod +x "$BIN_TOOL"
    fi
fi

# build storefront
[[ ${SHOPWARE_SKIP_BUNDLE_DUMP-""} ]] || "${BIN_TOOL}" bundle:dump
DATABASE_URL="" "${BIN_TOOL}" feature:dump

if [[ $(command -v jq) ]]; then
    OLDPWD=$(pwd)
    cd "$PROJECT_ROOT" || exit

    jq -c '.[]' "var/plugins.json" | while read -r config; do
        srcPath=$(echo "$config" | jq -r '(.basePath + .storefront.path)')

        # the package.json files are always one upper
        path=$(dirname "$srcPath")
        name=$(echo "$config" | jq -r '.technicalName' )

        if [[ -f "$path/package.json" && ! -d "$path/node_modules" && $name != "storefront" ]]; then
            echo "=> Installing npm dependencies for ${name}"

            npm install --prefix "$path"
        fi
    done
    cd "$OLDPWD" || exit
else
    echo "Cannot check extensions for required npm installations as jq is not installed"
fi

npm --prefix "${STOREFRONT_ROOT}"/Resources/app/storefront install
node "${STOREFRONT_ROOT}"/Resources/app/storefront/copy-to-vendor.js
npm --prefix "${STOREFRONT_ROOT}"/Resources/app/storefront run production
[[ ${SHOPWARE_SKIP_ASSET_COPY-""} ]] ||"${BIN_TOOL}" assets:install
[[ ${SHOPWARE_SKIP_THEME_COMPILE-""} ]] || "${BIN_TOOL}" theme:compile