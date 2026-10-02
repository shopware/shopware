#!/usr/bin/env bash
# Collapse this workflow's earlier comments on the same issue or pull request.
#
# Someone scrolling a thread sees an image and assumes it reflects the current code, so older runs
# are minimised as OUTDATED, leaving exactly one visible set.
#
# Env: ISSUE (required), REPO (required, owner/name), WORKFLOW (default sw-screenshot),
#      GH_TOKEN (required).
set -euo pipefail

: "${ISSUE:?ISSUE is required}"
: "${REPO:?REPO is required}"
# gh aw stamps this into every comment it posts. Matching it rather than a marker the agent has to
# remember means a comment still gets collapsed when the agent fails before composing a full body.
WORKFLOW=${WORKFLOW:-sw-screenshot}
MARKER="gh-aw-workflow-call-id: ${REPO}/${WORKFLOW}"

owner=${REPO%%/*}
name=${REPO##*/}

# Comments are listed oldest-first; every one of ours except the last is stale by definition.
ids=()
while IFS= read -r id; do
  [ -n "$id" ] && ids+=("$id")
done < <(
  gh api --paginate "repos/${REPO}/issues/${ISSUE}/comments" \
    --jq ".[] | select(.body | contains(\"${MARKER}\")) | select(.user.login | endswith(\"[bot]\")) | .node_id"
)

count=${#ids[@]}
[ "$count" -gt 1 ] || { echo "nothing to collapse (${count} prior comment(s))"; exit 0; }

for id in "${ids[@]:0:$((count - 1))}"; do
  gh api graphql -f query='
    mutation($id: ID!) {
      minimizeComment(input: { subjectId: $id, classifier: OUTDATED }) {
        minimizedComment { isMinimized }
      }
    }' -f id="$id" >/dev/null && echo "collapsed ${id}" || echo "::warning::could not collapse ${id}"
done

echo "collapsed $((count - 1)) earlier comment(s) on ${owner}/${name}#${ISSUE}"
