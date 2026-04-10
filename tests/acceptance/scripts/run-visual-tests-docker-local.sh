#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
APP_URL="${APP_URL:-http://web:8000}"
COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-visualci}"
FILTER="${FILTER:-}"
TEST_PATH="${TEST_PATH:-}"
UPDATE_SNAPSHOTS="${UPDATE_SNAPSHOTS:-false}"
PLAYWRIGHT_VERSION="${PLAYWRIGHT_VERSION:-}"
CLEAN="${CLEAN:-1}"
SHOPWARE_BOOTSTRAP="${SHOPWARE_BOOTSTRAP:-auto}"

export COMPOSE_PROJECT_NAME

cd "${ROOT_DIR}"

if [ "${CLEAN}" = "1" ]; then
    echo "Removing existing visual test stack and volumes"
    docker compose -f compose.yaml -f .github/docker/compose.visual-ci.yaml down -v
fi

echo "Starting Shopware with Docker compose"
docker compose -f compose.yaml -f .github/docker/compose.visual-ci.yaml up -d

BOOTSTRAP_CMD="setup"
if [ "${SHOPWARE_BOOTSTRAP}" = "reset" ]; then
    BOOTSTRAP_CMD="reset"
elif [ "${SHOPWARE_BOOTSTRAP}" = "auto" ]; then
    if [ -f vendor/autoload.php ] \
        && [ -x src/Administration/Resources/app/administration/node_modules/.bin/eslint ] \
        && [ -x src/Storefront/Resources/app/storefront/node_modules/.bin/webpack ]; then
        BOOTSTRAP_CMD="reset"
    fi
fi

echo "Bootstrapping Shopware with composer ${BOOTSTRAP_CMD}"
docker compose -f compose.yaml -f .github/docker/compose.visual-ci.yaml exec -T web composer "${BOOTSTRAP_CMD}"

cd tests/acceptance

if [ -z "${PLAYWRIGHT_VERSION}" ]; then
    PLAYWRIGHT_VERSION="$(jq -r '.packages["node_modules/playwright-core"].version' package-lock.json)"
fi

echo "Waiting for ${APP_URL}"
READY=0
for _ in {1..60}; do
    if docker compose -f "${ROOT_DIR}/compose.yaml" -f "${ROOT_DIR}/.github/docker/compose.visual-ci.yaml" exec -T web sh -lc 'curl -kfsS -o /dev/null "$APP_URL"'; then
        READY=1
        break
    fi
    sleep 2
done

if [ "${READY}" -ne 1 ]; then
    echo "Shopware did not become ready in time"
    cd "${ROOT_DIR}"
    docker compose -f compose.yaml -f .github/docker/compose.visual-ci.yaml ps
    exit 1
fi

mkdir -p "${HOME}/.npm"

PLAYWRIGHT_ARGS=(test)

if [ -n "${TEST_PATH}" ]; then
    PLAYWRIGHT_ARGS+=("${TEST_PATH}")
fi

PLAYWRIGHT_ARGS+=(--project=Visual)

if [ "${UPDATE_SNAPSHOTS}" = "true" ]; then
    PLAYWRIGHT_ARGS+=(--update-snapshots)
fi

if [ -n "${FILTER}" ]; then
    PLAYWRIGHT_ARGS+=("--grep=${FILTER}")
fi

docker run -it --rm --network "${COMPOSE_PROJECT_NAME}_default" \
    -w /ats \
    -v "$(pwd):/ats" \
    -v "${HOME}/.npm:/root/.npm" \
    -e CI=1 \
    -e APP_URL="${APP_URL}" \
    -e SHOPWARE_PLAYWRIGHT_IGNORE_HTTPS_ERRORS=1 \
    "mcr.microsoft.com/playwright:v${PLAYWRIGHT_VERSION}-noble" \
    bash -lc 'npm ci && npx playwright "$@"' bash "${PLAYWRIGHT_ARGS[@]}"
