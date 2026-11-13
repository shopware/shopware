---
name: shopware-pr-hygiene
description: >
  Prepare or update Shopware pull requests. Use when opening a PR ("create a PR",
  "open a PR", "open a draft PR"), marking a draft ready for review ("mark this PR ready"),
  or when the user asks for a PR description, PR text, PR body, PR template, or PR title
  ("output a PR description", "write the PR text", "give me a PR title", "fill in the PR template"),
  a conventional title like `fix:` or `feat(admin):`, or when answering review feedback, reviewer comments, or
  requested changes, or when fixing CI failures on an open PR and adding a follow-up
  commit. Also covers commit message bodies and AI-attribution trailers in a PR. Not for
  writing the code or tests of a change, or for release notes.
license: MIT
---

# Shopware PR Hygiene

Keep PR metadata predictable.

## Rules

- Follow `.github/PULL_REQUEST_TEMPLATE.md` closely, including the instructions for agents at its bottom.
  They are the single source for how to write the description.
- Do not add extra PR description sections such as a separate validation section.
- Use a conventional PR title when requested, for example `fix: allow TestBootstrapper to activate Composer plugins`.
- After review feedback or CI failures, create a follow-up commit explaining that specific fix. Do not amend or force-push existing commits unless the user explicitly asks.
- Use a commit message body when it adds useful context; keep one-line commits when the subject is enough.
- Never add AI-agent attribution trailers such as `Co-authored-by`, `Co-committed-by`, or `Signed-off-by`.
- Never open a public PR for a security fix. Report it privately as described in [SECURITY.md](../../../SECURITY.md).

## Before opening a PR or marking it ready

- Apply this check when opening any PR, including drafts, and when marking a draft ready for review.
- Explicitly load and follow [sw-review](../sw-review/SKILL.md) for one review pass against the intended PR target branch.
  Record the reviewed head and target commits with the result.
  Reuse an existing review when both commits are unchanged.
  When either commit changes, review only subsequent changes and affected interactions, including relevant target-branch changes.
  Keep review rules sourced from the PR target's merge base; review the full PR diff when the previous review range is unknown.
- Resolve confirmed blocking and major findings within the task's scope.
  Verify the resulting fix diff and affected context once, then stop the review loop.
  Do not automatically start another full review after each fix.
- Surface unresolved findings and review coverage gaps to the user before opening the PR or marking it ready.
- Requests to write or edit PR text alone do not require this review.
