---
name: shopware-pr-hygiene
description: Prepare or update Shopware pull requests. Use when writing or editing a PR description or PR text (incl. "output a PR description"), choosing a PR title, responding to review feedback, or updating a PR after CI failures.
license: MIT
---

# Shopware PR Hygiene

Keep PR metadata predictable.

## Rules

- Follow `.github/PULL_REQUEST_TEMPLATE.md` closely.
- Do not add extra PR description sections such as a separate validation section.
- Use a conventional PR title when requested, for example `fix: allow TestBootstrapper to activate Composer plugins`.
- After review feedback or CI failures, create a follow-up commit explaining that specific fix. Do not amend or force-push existing commits unless the user explicitly asks.
- Use a commit message body when it adds useful context; keep one-line commits when the subject is enough.
- Never add AI-agent attribution trailers such as `Co-authored-by`, `Co-committed-by`, or `Signed-off-by`.
