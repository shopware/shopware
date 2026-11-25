# shellcheck shell=bash

# Commits every change of the work tree with the message $1.
commit_all() {
    git add -A
    git commit -q -m "$1"
}
