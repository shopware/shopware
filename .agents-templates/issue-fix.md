# GUI-First `issue-fix` Workflow

This repository uses a GUI-first GitHub issue fixing workflow for Claude and Codex.

The canonical operation name is `issue-fix`.

Preferred invocations:

- `issue-fix 12345`
- `/issue-fix 12345`
- `Run issue-fix for GitHub issue 12345`

This workflow is intentionally agent-side. It must use local `gh`, `git`, and repository inspection from the active Claude or Codex session instead of a Symfony command in Shopware core.

`gh` is a required prerequisite for this workflow.

## Input

- A single GitHub issue reference from the current repository:
  - an issue number, or
  - a full GitHub issue URL

If the issue reference is missing, the agent must ask for it before proceeding.

If a full GitHub issue URL is provided, the agent should extract the issue number from it and continue with the same workflow.

If `gh` is missing or not authenticated, the agent must pause and guide the user through installing or authenticating `gh` before continuing. The agent should not silently switch to a different issue-ingestion path in v1.

## Prerequisites

Before reading the issue, the agent must verify:

- `gh` is installed locally
- `gh` is authenticated for GitHub access

The agent should prefer checking `gh` from the same environment it plans to use for GitHub commands. If a sandboxed check reports an invalid or missing token, but the failure could plausibly come from keychain or credential-store isolation, the agent must retry the `gh` auth check unsandboxed before concluding that authentication is actually broken.

If either check fails:

- stop before branch creation or code changes
- explain that `issue-fix` requires `gh`
- guide the user through the missing setup step
- resume the workflow only after `gh` is ready

## `gh` Authentication Troubleshooting

When `gh` authentication appears broken, the agent must distinguish between a real auth problem and an execution-environment problem.

Treat it as an environment problem first when:

- sandboxed `gh auth status` says the token is invalid
- sandboxed `gh auth token` reports no token found
- the user expects keychain-backed or credential-store-backed authentication

In that case, the agent should:

1. retry the `gh` auth check unsandboxed
2. use the unsandboxed result as the source of truth for GitHub access
3. continue the workflow with unsandboxed `gh` commands if auth is healthy there

Only tell the user their GitHub authentication is broken when the unsandboxed check also fails.

## Issue Ingestion

When reading the issue, the agent must collect:

- the issue title
- the issue body
- labels and status
- linked metadata visible through GitHub
- issue comments, if any

Comments are part of the required context. The agent must read them before deciding whether the issue is still actionable.

## Required Workflow

The agent must perform these steps in order:

1. Read the GitHub issue with `gh`.
2. Validate that the issue is actionable for this repository.
3. Detect ambiguity or missing context.
4. Ask the user before proceeding if important ambiguity remains.
5. Determine the correct git repository for the fix.
6. Decide whether the work is a `fix/...` or `feat/...` branch.
7. Create and switch to the branch in the correct repository.
8. Analyze the root cause in the current codebase.
9. Explain how and why the issue happens.
10. Implement the fix using current repository patterns and Shopware 6 best practices.
11. Run the relevant checks for the touched file types.
12. Perform a self code review of the produced diff.
13. Fix the review findings before finishing.
14. Stage the changes for user review.
15. Explain the final fix, why it was chosen, and why it resolves the issue.
16. Suggest one very short Conventional Commit message for the staged change.
17. Suggest a short pull request title and description.
18. Commit and create the pull request only if the user explicitly signs off on the staged changes.

## Validation Rules

The issue is actionable only if all of the following are true:

- `gh` is installed and authenticated.
- The issue can be read successfully from GitHub.
- The issue belongs to the current repository context.
- The issue contains enough context to start analysis.
- The issue is asking for repository work rather than unrelated support or operations work.
- The issue does not already appear resolved, obsolete, or superseded by newer context.
- There is no existing in-progress pull request that already covers the same fix direction.

If validation fails, stop and report the blocking reason. Do not create a branch and do not edit files.

## Triage Before Fixing

Before creating a branch, the agent must actively evaluate whether the issue should be fixed at all.

The agent may recommend not proceeding when the issue appears to be:

- already fixed
- outdated
- no longer worth fixing
- superseded by newer decisions or comments
- already being worked on in an existing pull request

If the agent finds strong evidence for one of those cases, it should:

- explain the evidence clearly
- link the conclusion back to the issue body, comments, repository state, or existing PR context
- recommend not proceeding with a fix
- ask the user whether they still want implementation work

In these cases, the agent must not create a branch or edit files unless the user explicitly wants to continue anyway.

## Ambiguity Rules

The agent must stop and ask the user before proceeding when any of these are true:

- The expected behavior is unclear.
- The issue could reasonably map to more than one implementation direction.
- The issue depends on screenshots, linked discussions, or outside context that is not available locally.
- The issue could be either a bugfix or a feature and the branch type cannot be inferred confidently.
- The requested scope is broad enough that more than one substantial fix would be reasonable.
- The issue comments meaningfully change, narrow, or contradict the original issue description.
- There is evidence that a PR may already be in progress, but the agent cannot confidently determine whether it fully covers the issue.

The clarification request should be concrete and short. Do not continue to implementation until the ambiguity is resolved.

## Existing PR Check

Before implementation, the agent should check whether there is already a pull request in progress for the issue or the same problem area.

If a relevant PR already exists, the agent should prefer recommending that the user review or continue that work instead of starting a parallel fix branch.

## Branch Naming

Branch names must use a short kebab-case summary derived from the issue title.

- Bugfix work: `fix/<short-issue-summary>`
- Feature work: `feat/<short-issue-summary>`

Guidance:

- Prefer `fix/...` when the issue describes broken, missing, incorrect, or regressed behavior.
- Prefer `feat/...` when the issue requests new behavior or a product enhancement.
- If the classification is not clear from the issue, ask the user before creating the branch.
- Keep the slug short, stable, and implementation-agnostic.

## Repository Targeting

Before creating a branch, the agent must determine which git repository should contain the fix.

Default:

- If the issue affects Shopware core, create the branch in the Shopware root repository.

Plugin rule:

- If the issue clearly affects a plugin located under `custom/` and that plugin has its own git repository, create and switch to the branch inside the plugin repository instead of the Shopware root repository.

The agent must not create the branch in the Shopware root repository when:

- the intended code changes belong only to a plugin repository under `custom/`
- creating the branch in the root repo would leave the root worktree unchanged

When targeting a plugin repository, the agent should:

- identify the correct plugin directory first
- confirm that the plugin directory is its own git repository
- create the `fix/...` or `feat/...` branch inside that plugin repository
- perform status, diff, staging, and final reporting relative to that plugin repository

If the correct target repository is unclear, the agent must ask before creating any branch.

## Implementation Rules

- Follow the existing patterns in the affected area of the repository.
- Apply Shopware 6 best practices from `AGENTS.md` and `coding-guidelines/`.
- Prefer events over decorators when extending behavior unless the timing requires decoration.
- Use DAL patterns instead of Doctrine ORM assumptions.
- Do not invent a parallel architecture when a local pattern already exists.

## Checks And Review

Run only the relevant checks for the touched file types, using the lint/test matrix from `AGENTS.md`.

At minimum, the self review must cover:

- behavioral correctness
- regressions and edge cases
- missing tests
- style or pattern mismatches with the surrounding code

If the self review finds issues, fix them before finishing.

## Sign-Off Gate

The default workflow ends with staged changes, a suggested Conventional Commit message, and a suggested pull request description.

The agent must not commit or create a pull request automatically.

It may commit and open the pull request only after the user clearly signs off on the produced changes.

Accepted sign-off examples:

- "looks good, commit it"
- "go ahead and create the PR"
- "please commit and open the PR"

Until that sign-off exists, the agent must stop after staging and explanation.

## Commit And PR Rules

After explicit user sign-off, the agent should:

1. commit the staged changes using the suggested Conventional Commit message unless the user requested a different message
2. create a pull request
3. use the Conventional Commit message as the PR title unless the user requested a different title
4. include a short PR description
5. include a `Fixes: <issue-reference>` line in the PR description

The PR description should stay short and include:

- a concise summary of the fix
- the root cause in one short paragraph or bullet
- the checks that were run
- `Fixes: <issue-reference>`

## Output Requirements

Before implementation, the agent should create a brief using `.agents-templates/issue-fix/templates/brief.md.tpl`.

If clarification is needed, the agent should use `.agents-templates/issue-fix/templates/clarification-questions.md.tpl`.

Before finishing, the agent should produce:

- a self review checklist using `.agents-templates/issue-fix/templates/self-review-checklist.md.tpl`
- a final explanation using `.agents-templates/issue-fix/templates/final-summary.md.tpl`
- one very short Conventional Commit message suggestion
- one short pull request description written inline

The final state must be:

- branch created and checked out
- changes staged
- no commit created
- clear explanation ready for user review
- one suggested Conventional Commit message ready for copy/paste
- one suggested pull request description ready for copy/paste

After explicit user sign-off, the workflow may continue to:

- create the commit
- create the pull request
