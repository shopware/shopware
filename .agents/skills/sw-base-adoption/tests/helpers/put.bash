# shellcheck shell=bash

# Writes $2 to the file $1, creating its directory.
put() {
    mkdir -p "$(dirname "$1")"
    printf '%s\n' "$2" >"$1"
}
