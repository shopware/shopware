#!/usr/bin/env bash

CWD="$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"

export PROJECT_ROOT="${PROJECT_ROOT:-"$(dirname "$CWD")"}"
export ENV_FILE=${ENV_FILE:-"${PROJECT_ROOT}/.env"}

# shellcheck source=functions.sh
source "${PROJECT_ROOT}/bin/functions.sh"

load_dotenv "$ENV_FILE"

export HOST=${HOST:-"localhost"}
export ESLINT_DISABLE
export PORT
export APP_URL

bin/console bundle:dump
bin/console feature:dump || true

if [[ $(command -v jq) ]]; then
    OLDPWD=$(pwd)
    cd "$PROJECT_ROOT" || exit

    jq -c '.[]' "var/plugins.json" | while read -r config; do
        srcPath=$(echo "$config" | jq -r '(.basePath + .administration.path)')

        # the package.json files are always one upper
        path=$(dirname "$srcPath")
        name=$(echo "$config" | jq -r '.technicalName' )

        if [[ -f "$path/package.json" && ! -d "$path/node_modules" && $name != "administration" ]]; then
            echo "=> Installing npm dependencies for ${name}"

            npm install --prefix "$path"
        fi
    done
    cd "$OLDPWD" || exit
else
    echo "Cannot check extensions for required npm installations as jq is not installed"
fi

if [ ! -d vendor/shopware/administration/Resources/app/administration/node_modules ]; then
    npm install --prefix vendor/shopware/administration/Resources/app/administration/
fi

npm run --prefix vendor/shopware/administration/Resources/app/administration/ dev
