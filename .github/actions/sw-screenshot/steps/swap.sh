#!/usr/bin/env bash
# Repoint the provisioned shop at the other ref and bring it back to a screenshotable state.
#
# One instance, one database: only the source tree moves, so everything seeded for the "before"
# screenshot is still there for the "after" one.
#
# Usage: swap.sh <head|base>
# Env: SHOP_DIR (required), PR_REPO (required, owner/name), BASE_SHA (required),
#      HEAD_REF (required, e.g. refs/pull/42/head), APP_PORT (default 8000).
set -euo pipefail

TARGET=${1:?usage: swap.sh <head|base>}
: "${SHOP_DIR:?SHOP_DIR is required}"
SHOP_DIR=$(cd "$SHOP_DIR" && pwd)
APP_PORT=${APP_PORT:-8000}
: "${BASE_SHA:?BASE_SHA is required}"
: "${HEAD_REF:?HEAD_REF is required}"
: "${PR_REPO:?PR_REPO is required}"

# Named explicitly rather than relying on the shop checkout's origin: the pull request lives in the
# repository running the workflow, which on a fork is not the one Shopware was cloned from.
PR_REMOTE="https://github.com/${PR_REPO}.git"

cd "$SHOP_DIR"

FROM=$(git rev-parse HEAD)

echo "::group::checkout ${TARGET}"
if [ "$TARGET" = head ]; then
  # The head may live in a fork, so fetch the pull request ref rather than a branch name.
  git fetch --no-tags --depth=1 "$PR_REMOTE" "+${HEAD_REF}:refs/sw-screenshot/head"
  git checkout --force --detach refs/sw-screenshot/head
else
  git fetch --no-tags --depth=1 "$PR_REMOTE" "+${BASE_SHA}:refs/sw-screenshot/base"
  git checkout --force --detach refs/sw-screenshot/base
fi
TO=$(git rev-parse HEAD)
echo "::endgroup::"

# Scope the rebuild to what moved: a storefront-only change must not pay for an admin build.
CHANGED=$(git diff --name-only "$FROM" "$TO")
changed_in() { grep -q "$1" <<<"$CHANGED"; }

if changed_in '^composer\.\(json\|lock\)$'; then
  echo "::group::composer install"
  composer install --no-interaction --no-progress
  echo "::endgroup::"
fi

echo "::group::migrate + dumps"
# Schema changes ride along with the source, so migrations must run before anything touches the DAL.
php bin/console database:migrate --all --no-interaction
php bin/console bundle:dump --no-interaction
php bin/console feature:dump --no-interaction
echo "::endgroup::"

if changed_in '^src/Storefront/'; then
  echo "::group::storefront assets"
  # Twig is read at runtime once the cache is cleared; only SCSS needs a compile.
  if changed_in '^src/Storefront/Resources/'; then
    (cd src/Storefront/Resources/app/storefront && npm ci --no-audit --no-fund && npm run production)
    php bin/console assets:install --no-interaction
  fi
  php bin/console theme:compile --sync --no-interaction
  echo "::endgroup::"
fi

# Every bundle's admin module — including src/Storefront/Resources/app/administration — is compiled
# into the one Administration build, so any of them changing means a full rebuild.
if changed_in '^src/[A-Za-z]*/Resources/app/administration/' || changed_in '^src/Administration/'; then
  echo "::group::administration build"
  # `composer build:js:admin` runs `npm run build` and no install of its own, so a head that moved a
  # dependency would otherwise build against the merge base's node_modules.
  if changed_in '^src/Administration/Resources/app/administration/package\(\.json\|-lock\.json\)$'; then
    (cd src/Administration/Resources/app/administration && npm ci --no-audit --no-fund)
  fi
  composer build:js:admin
  echo "::endgroup::"
fi

php bin/console cache:clear --no-interaction

for _ in $(seq 1 60); do
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "http://localhost:${APP_PORT}/admin" || echo 000)
  [ "$code" = 200 ] && { ready=1; break; }
  sleep 1
done
[ "${ready:-0}" = 1 ] || { echo "::error::shop unreachable after swapping to ${TARGET}"; exit 1; }

echo "swapped to ${TARGET} (${TO})"
