#!/bin/bash
TODAY=$(date +%Y-%m-%d)

NEXT_MONDAY=$(date -d "${TODAY} + 7 days" +%Y-%m)

# If it is not the last monday, just quit
if [[ $(date +%Y-%m) == "${NEXT_MONDAY}" ]]; then
  echo "It is not the last monday of the month"
  exit 0
fi

LATEST_TAG=$(git describe --tags "$(git rev-list --tags --max-count=1)")
REGEX="([0-9]+).([0-9]+).([0-9]+).([0-9]+)"
if [[ ${LATEST_TAG} =~ ${REGEX} ]]; then
  MAJOR="${BASH_REMATCH[2]}"
  MINOR="${BASH_REMATCH[3]}"
fi
NEXT_TAG="v6.${MAJOR}.$((MINOR + 1)).0"

SLACK_PAYLOAD=$(jq \
  --null-input \
  --arg branch "$(date --utc --date="${TODAY}" +'saas/%Y/%W')" \
  --arg tag "${NEXT_TAG}" \
  --arg release_date "$(date -d "${TODAY} + 7 days" +%F)" \
  '{"branch": $branch, "tag": $tag, "release_date": $release_date}')

curl --silent --request POST --url "${SLACK_LAST_DEPLOYMENT_WORKFLOW_URL}" --header "Content-Type: application/json" --data "${SLACK_PAYLOAD}"
