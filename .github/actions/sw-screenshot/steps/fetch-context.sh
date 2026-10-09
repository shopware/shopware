#!/usr/bin/env bash
# Assemble everything the agent needs about the issue or pull request, and pick its mode.
#
# The mode follows from the event, never from the agent's reading of the text. The chosen mode file
# is copied to mode.md, which the prompt tells the agent to read.
#
# Env: NUMBER (required), REPO (required), GH_TOKEN (required), SKILL_DIR
#      (default .agents/skills/sw-screenshot).
# Writes: context.md, mode.md, number.txt, and for pull requests base-sha.txt / head-ref.txt.
# Outputs (when GITHUB_OUTPUT is set): provision_ref — the merge base for a pull request, else trunk.
set -euo pipefail

: "${NUMBER:?NUMBER is required}"
: "${REPO:?REPO is required}"
SKILL_DIR=${SKILL_DIR:-.agents/skills/sw-screenshot}

printf '%s' "$NUMBER" > number.txt

is_pr=$(gh api "repos/${REPO}/issues/${NUMBER}" --jq 'if .pull_request then "true" else "false" end')

{
  echo "# Run context"
  echo
  echo "- Repository: ${REPO}"
  echo "- Number: #${NUMBER}"
  echo "- Mode: $([ "$is_pr" = true ] && echo "pull request" || echo issue)"
  echo
} > context.md

if [ "$is_pr" = true ]; then
  cp "${SKILL_DIR}/modes/pr.md" mode.md

  gh api "repos/${REPO}/pulls/${NUMBER}" \
    --jq '"## Pull request\n\n### Title\n\n" + .title + "\n\n### Body\n\n" + (.body // "_(empty)_")' >> context.md

  base_sha=$(gh api "repos/${REPO}/pulls/${NUMBER}" --jq '.base.sha')
  merge_base=$(gh api "repos/${REPO}/compare/${base_sha}...refs/pull/${NUMBER}/head" --jq '.merge_base_commit.sha')
  printf '%s' "$merge_base" > base-sha.txt
  printf 'refs/pull/%s/head' "$NUMBER" > head-ref.txt

  {
    echo
    echo "### Changed files"
    echo
    echo '```'
    gh api --paginate "repos/${REPO}/pulls/${NUMBER}/files" --jq '.[].filename'
    echo '```'
    echo
    echo "- Merge base: \`${merge_base}\`"
  } >> context.md

  # Repro steps usually live in the issue the pull request fixes, not in the pull request itself.
  # grep exits 1 when the body references nothing, which is a normal outcome here, not an error.
  linked=$(gh api "repos/${REPO}/pulls/${NUMBER}" \
    --jq '.body // ""' | grep -oiE '(fixes|closes|resolves) +#[0-9]+' | grep -oE '[0-9]+' | head -1 || true)

  if [ -n "$linked" ]; then
    {
      echo
      echo "## Linked issue #${linked}"
      echo
      gh api "repos/${REPO}/issues/${linked}" --jq '"### Title\n\n" + .title + "\n\n### Body\n\n" + (.body // "_(empty)_")'
    } >> context.md
  else
    echo -e "\n## Linked issue\n\nNone referenced." >> context.md
  fi
else
  cp "${SKILL_DIR}/modes/issue.md" mode.md

  gh api "repos/${REPO}/issues/${NUMBER}" \
    --jq '"## Issue\n\n### Title\n\n" + .title + "\n\n### Body\n\n" + (.body // "_(empty)_")' >> context.md

  # Assigned first so a failed fetch takes the step down: repro steps live in the comments more often
  # than in the body, and an empty section reads to the agent as an issue with nothing on it.
  comments=$(gh api --paginate "repos/${REPO}/issues/${NUMBER}/comments" \
    --jq '.[] | "### @" + .user.login + "\n\n" + (.body // "")')

  {
    echo
    echo "## Comments"
    echo
    echo "${comments:-_(none)_}"
  } >> context.md
fi

# A pull request is photographed from its merge base, so the "before" shows where the branch started
# rather than whatever else has landed on trunk since.
if [ -n "${GITHUB_OUTPUT:-}" ]; then
  if [ -f base-sha.txt ]; then
    echo "provision_ref=$(cat base-sha.txt)" >> "$GITHUB_OUTPUT"
  else
    echo "provision_ref=trunk" >> "$GITHUB_OUTPUT"
  fi
fi

echo "wrote context.md ($(wc -c < context.md) bytes) and mode.md"
