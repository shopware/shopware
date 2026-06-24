---
name: shopware-pr-hygiene
description: Prepare or update Shopware pull requests. Use when writing or editing a PR description or PR text (incl. "output a PR description"), choosing a PR title, doing the pre-open check for missing tests or release notes, responding to review feedback, or updating a PR after CI failures.
license: MIT
---

# Shopware PR Hygiene

Keep PR metadata predictable.

## Rules

- Follow `.github/PULL_REQUEST_TEMPLATE.md` closely.
- Do not add extra PR description sections such as a separate validation section.
- Use a conventional PR title when requested, for example `fix: allow TestBootstrapper to activate Composer plugins`.
- After review feedback or CI failures, create a follow-up commit explaining that specific fix. Do not amend or force-push existing commits unless the user explicitly asks.

## Final Pass Before Opening

- Review the diff once before opening the PR and decide whether more verification or documentation is needed.
- Invoke `shopware-phpunit-tests` when PHP behavior, exceptions, feature flags, migrations, DBAL boundaries, or test structure changed.
- Invoke `shopware-admin-js` when Administration JS, TypeScript, or Vue behavior changed.
- Invoke `shopware-release-docs` when the change may affect extension authors, API consumers, operators, storefront/theme developers, public APIs, deprecations, removals, configuration, or upgrade steps.
- If no more tests or docs are needed, keep the PR body concise and within the template.
