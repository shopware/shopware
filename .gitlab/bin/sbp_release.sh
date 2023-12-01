#!/usr/bin/env sh
set -eu

print_usage() {
    echo "Usage:"
    echo "sbp_release.sh create [VERSION]"
    echo "sbp_release.sh publish [VERSION]"
    exit 1
}

get_sbp_id() {
    VERSION_NAME=$1

    SBP_ID=$(curl --silent --request GET \
         --url ${CI_ENVIRONMENT_URL}/static/softwareversions \
         --header 'Accept: application/json' \
         --header "X-Shopware-Token: ${SBP_TOKEN}" | jq ".[] | select(.name == \"${VERSION_NAME}\") | .id")
    
    echo $SBP_ID
}

if [ -z "$2" ]; then
    print_usage
fi

PLATFORM_TAG=$2
PLATFORM_VERSION=${PLATFORM_TAG#v*}
PLATFORM_MAJOR_VERSION=$(echo $PLATFORM_VERSION | cut -d '.' -f1-2)

SBP_PARENT_ID=$(get_sbp_id $PLATFORM_MAJOR_VERSION)

case "$1" in
    create)
        PAYLOAD="{
            \"name\": \"${PLATFORM_VERSION}\",
            \"parent\": \"${SBP_PARENT_ID}\",
            \"status\": {
                \"name\": \"visible_for_manufacturers\"
            }
        }"
        curl --request POST \
            --url "${CI_ENVIRONMENT_URL}/static/softwareversions" \
            --header 'Content-Type: application/json' \
            --header "X-Shopware-Token: ${SBP_TOKEN}" \
            --data "${PAYLOAD}"
        ;;
    publish)
        SBP_VERSION_ID=$(get_sbp_id $PLATFORM_VERSION)
        TODAY=$(date "+%Y-%m-%d")
        PAYLOAD="{
            \"name\": \"${PLATFORM_VERSION}\",
            \"parent\": \"${SBP_PARENT_ID}\",
            \"status\": {
                \"name\": \"public\"
            },
            \"releaseDate\": \"${TODAY}\"
        }"
        curl --request POST \
            --url "${CI_ENVIRONMENT_URL}/static/softwareversions/${SBP_VERSION_ID}" \
            --header 'Content-Type: application/json' \
            --header "X-Shopware-Token: ${SBP_TOKEN}" \
            --data "${PAYLOAD}"
        ;;
    *)
        print_usage
        ;;
esac
