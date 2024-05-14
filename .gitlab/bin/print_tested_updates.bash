#!/usr/bin/env bash

PREV_MAJOR="${PREV_MAJOR:-"v6.5."}"
CUR_MAJOR="${CUR_MAJOR:-"v6.6."}"

# make sure tag is prefixed with v
PREV_MAJOR="v${PREV_MAJOR#v}"
CUR_MAJOR="v${CUR_MAJOR#v}"

get_tags() {
    git -c 'versionsort.suffix=-' ls-remote --exit-code --refs --sort='version:refname' --tags 2>/dev/null | cut --delimiter='/' --fields=3 | grep -v -i -E '(dev|beta|alpha)'
}

get_tags_without_rc() {
    git -c 'versionsort.suffix=-' ls-remote --exit-code --refs --sort='version:refname' --tags 2>/dev/null | cut --delimiter='/' --fields=3 | grep -v -i -E 'rc'
}


print_min_max_tag() {
    local min_tag=$(get_tags_without_rc | grep -E "^$2" | head -n 1)
    if [[ -z $min_tag ]]; then
        min_tag=$(get_tags | grep -E "^$2" | head -n 1)
    fi

    echo "${1}_MIN_TAG=$min_tag"

    local max_tag=$(get_tags_without_rc | grep -E "^$2" | tail -n 1)
    if [[ -z $max_tag ]]; then
        max_tag=$(get_tags | grep -E "^$2" | tail -n 1)
    fi

    echo "${1}_MAX_TAG=$max_tag"
}


print_min_max_tag PREV_MAJOR "$PREV_MAJOR"
print_min_max_tag CUR_MAJOR "$CUR_MAJOR"
