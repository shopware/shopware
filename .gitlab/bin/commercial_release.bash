#!/usr/bin/env bash

set -x
set -e

PLATFORM_TAG=$1
COMMERCIAL_REMOTE_URL=${2:-"https://gitlab.shopware.com/shopware/6/product/commercial"}
COMMERCIAL_VERSION="$(echo ${PLATFORM_TAG} | cut -d '.' -f2,3,4)"
COMMERCIAL_TAG="v${COMMERCIAL_VERSION}"

# get the first branch, that contains the $PLATFORM_TAG
BRANCH=$(git branch --contains "${PLATFORM_TAG}" | tr -d '[:blank:]' | grep -E '(^saas/|^next-.*release)' | sort | head -n 1)

if [ -z "${BRANCH}" ]; then
    echo "Didn't find a branch containing the ${PLATFORM_TAG} tag."
    exit 1
fi

# clone the matching commercial branch
echo "Clone commercial branch '${BRANCH}' from $COMMERCIAL_REMOTE_URL"
git clone $COMMERCIAL_REMOTE_URL commercial --branch $BRANCH

cd commercial

# allow the current minor or newer (required for the update, it breaks if you only allow patch releases for some reason)
CORE_REQUIRE="shopware/core:~${PLATFORM_TAG}"

# composer will put it into the composer.json otherwise
composer config --global --no-plugins allow-plugins.symfony/runtime false

composer config version ${COMMERCIAL_VERSION} --no-interaction

composer require "$CORE_REQUIRE" --no-interaction --no-update

# Add Changelog entry
sed -i "1s/^/# $COMMERCIAL_VERSION\n\n* Release for platform $PLATFORM_TAG\n\n/" CHANGELOG.md

git add composer.json

ISSUE_KEY=NEXT-29136
git commit -m "$ISSUE_KEY - Release $COMMERCIAL_VERSION"

git tag $COMMERCIAL_TAG

# also tag with platform tag so that it's easier to find matching versions in platform and commercial
git tag $PLATFORM_TAG

git push origin $BRANCH
git push origin refs/tags/$COMMERCIAL_TAG
git push origin refs/tags/$PLATFORM_TAG
