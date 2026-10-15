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
- Write the description for human reviewers. Keep it short and give the context the diff cannot show: why the change is needed, the decisions behind it, and the alternatives you rejected and why.
- Do not walk through the diff. Reviewers read the code, so skip small changes such as renamed headings, single files, or test cases.
- Always call out changes with a big blast radius, such as public API, configuration, database schema, events, caching, or behaviour other features rely on.
- Never describe the work session. Drafts and fixes that happened before the commit are not part of the change.
- Tick only the checklist items that are true for this PR.
- Use a conventional PR title when requested, for example `fix: allow TestBootstrapper to activate Composer plugins`.
- After review feedback or CI failures, create a follow-up commit explaining that specific fix. Do not amend or force-push existing commits unless the user explicitly asks.
- Use a commit message body when it adds useful context; keep one-line commits when the subject is enough.
- Never add AI-agent attribution trailers such as `Co-authored-by`, `Co-committed-by`, or `Signed-off-by`.
- Never open a public PR for a security fix. Report it privately as described in [SECURITY.md](../../../SECURITY.md).
