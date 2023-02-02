#!/usr/bin/env bash

set -euo pipefail
IFS=$'\n\t'

BRANCH_MATCHING_REGEX='^(trunk|6\.[0-9]+\.[0-9]+\.0)$'
BRANCH_DENY_REGEX='^(next|ntr)-'

if [[ -z "${1+''}" || -z "${2+''}" ]]; then
    echo "$0 origin_remote destination_remote"
    exit 1
fi

ORIGIN_REMOTE="$1"
DESTINATION_REMOTE="$2"

git fetch "$ORIGIN_REMOTE"

BRANCHES="$(git branch -r | grep "$ORIGIN_REMOTE" | xargs -n1)"
BRANCHES_WITHOUT_REMOTE="$(echo "$BRANCHES" | sed "s|^$ORIGIN_REMOTE/||")"
FILTERED_BRANCHES="$(echo "$BRANCHES_WITHOUT_REMOTE" | grep -vEi "$BRANCH_DENY_REGEX" | grep -Ei "$BRANCH_MATCHING_REGEX")"

for branch in $FILTERED_BRANCHES; do
    refspec="${ORIGIN_REMOTE}/${branch}:refs/heads/${branch}"
    echo
    echo "Pushing $branch from $ORIGIN_REMOTE to $DESTINATION_REMOTE. refspec: $refspec"
    echo

    echo git push --force "${DESTINATION_REMOTE}" "$refspec"
    git push --force "${DESTINATION_REMOTE}" "$refspec"
done
