---
name: improve-pr
description: Update an existing Shopware bugfixer pull request based on reviews, comments, failed checks, or an explicit instruction.
---

You are running inside a clean `shopware/shopware` checkout on the existing PR branch. The skill arguments include `prUrl`, `prNumber`, `repository`, `baseBranch`, `branchName`, PR metadata, and an optional `instruction`.

## Shared Rules

The workflow imports and injects the shared Bugfixer rules from `tools/bugfixer/src/skills/shared-rules.md`. Follow those rules throughout this skill.

## Workflow

1. Read the root `AGENTS.md` and any scoped `AGENTS.md` files relevant to files you inspect or change.
2. Read the current PR state using non-interactive commands:
    - `gh pr view <prNumber> --json ...`
    - `gh pr diff <prNumber>`
    - `gh pr checks <prNumber> --json ...` without `--watch`
    - review and comment APIs when needed
    - `git diff --name-only origin/<baseBranch>...HEAD` to verify whether GitHub should show changed files
3. Determine the reason for this improvement pass:
    - If `instruction` is present, treat it as the primary request.
    - Otherwise, use maintainer review comments and failed checks.
    - If there is no actionable feedback, return `no_changes`.
    - If the PR already has no file diff against `origin/<baseBranch>`, return `no_changes`, comment on the PR, and explain whether the branch appears to be already contained in base or merely has an equivalent tree.
4. Apply one focused follow-up. Do not recreate the PR, rename the branch, or open a new PR.
5. Run targeted validation for the change you made. Do not run broad full-suite commands unless the feedback specifically requires them.
6. Commit the follow-up if files changed.
7. Push the same `branchName` to `origin`.
8. Comment on the PR with a concise summary and validation results. If your follow-up leaves no changed files, explicitly explain why there is no remaining diff.
9. Immediately return the required structured result. Do not inspect, watch, or wait for PR checks after pushing.

## Policy Rules

- For Administration and Storefront dependency changes, treat package bumps as high-risk because they can affect extensions and downstream integrations.
- If feedback says a dependency bump is not acceptable, do not replace it with another broad dependency bump. Seek a narrower mitigation, revert the risky bump, or return `failed`/`no_changes` with a clear explanation when no safe patch exists.
- Do not close the PR, remove labels, dismiss reviews, mark the PR ready/draft, or mutate unrelated metadata.
- Do not force-push unrelated history. A normal push of a follow-up commit is preferred.

## Required Result

Return the structured result requested by the workflow:

- `status`: `updated_pr`, `no_changes`, or `failed`.
- `branchName`: the exact branch name used.
- `prUrl`: the PR URL.
- `commentUrl`: PR comment URL when a comment was created.
- `summary`: short explanation of what happened.
- `validation`: list of commands with `passed`, `failed`, or `not_run` outcomes.

Return this result as the final action of the skill. Do not keep working after the result is ready.
