#!/usr/bin/env sh
set -eu

PLATFORM_TAG=$2
PLATFORM_VERSION=${PLATFORM_TAG#v*}
PLATFORM_MAJOR_VERSION=$(echo $PLATFORM_VERSION | cut -d '.' -f1-2 | sed 's/\./-/g')

CHANGELOG_ENTRIES=$(sed -n "/## ${PLATFORM_VERSION}/, /##/{ /## ${PLATFORM_VERSION}/! { /##/! p } }" CHANGELOG.md)
RELEASE_MESSAGE_HEADER="See the [UPGRADE.md](./UPGRADE-${PLATFORM_MAJOR_VERSION}.md) for all important technical changes."

print_usage() {
    echo "Usage:"
    echo "create_github_release.sh draft [VERSION]"
    echo "create_github_release.sh publish [VERSION]"
    exit 1
}

case "$1" in
    draft)
        PAYLOAD=$(jq --null-input \
                     --arg version_tag "${PLATFORM_TAG}" \
                     --arg release_body "${RELEASE_MESSAGE_HEADER}\n\n${CHANGELOG_ENTRIES}" \
                     '{"tag_name": $version_tag, "target_commitish": "trunk", "name": "Release \($version_tag)", "body": $release_body, "draft": true, "prerelease": false, "generate_release_notes": false}' | sed 's/\\\\/\\/g')
        curl --request POST \
            --url "https://api.github.com/repos/shopware/shopware/releases" \
            --header "Accept: application/vnd.github+json" \
            --header "Authorization: Bearer ${GITHUB_SYNC_TOKEN}" \
            --header 'X-GitHub-Api-Version: 2022-11-28' \
            --data "${PAYLOAD}"
        ;;
    publish)
        GITHUB_RELEASE_ID=$(curl --silent \
            --request GET \
            --url "https://api.github.com/repos/shopware/shopware/releases" \
            --header "Accept: application/vnd.github+json" \
            --header "Authorization: Bearer ${GITHUB_SYNC_TOKEN}" \
            --header 'X-GitHub-Api-Version: 2022-11-28' | jq --arg version_tag "${PLATFORM_TAG}" '.[] | select(.tag_name == $version_tag) | .id')
        curl --request PATCH \
            --url "https://api.github.com/repos/shopware/shopware/releases/${GITHUB_RELEASE_ID}" \
            --header "Accept: application/vnd.github+json" \
            --header "Authorization: Bearer ${GITHUB_SYNC_TOKEN}" \
            --header 'X-GitHub-Api-Version: 2022-11-28' \
            --data '{"draft": false}'
        ;;
    *)
        print_usage
        ;;
esac
