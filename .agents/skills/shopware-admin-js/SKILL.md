---
name: shopware-admin-js
description: Apply Shopware Administration JS/TS/Vue coding rules. Use when editing Admin UI code (.js/.ts/.vue) under src/Administration, including Jest specs and ACL-backed components.
license: MIT
---

# Shopware Admin JS

Keep general Administration structure, tech stack, docs links, and scripts in `src/Administration/Resources/app/administration/AGENTS.md`.

## Code

- Use TypeScript for new code.
- Do not introduce public API breaks without prior discussion.
- Model new code on the reference implementations listed in `src/Administration/Resources/app/administration/AGENTS.md`, not on the code next to it.
- For Admin UI that reads or persists DAL entities or associations, update matching ACL privilege mapping and migrations for existing roles when needed.

## Tests

- Write Jest tests for new frontend behavior and bug fixes.
- For snippet-only changes or new translations using an existing error-rendering path, run snippet linting instead of adding tests that only assert translated text.
- Keep tests next to the code under test with `.spec.ts` when adding new TypeScript tests.
- Split very large specs into a `.spec/` directory by behavior group.

## Detailed Guidelines

- Read `coding-guidelines/administration/architecture.md` when changing Admin architecture, component registration, services, state, or module patterns.
- Read `coding-guidelines/administration/testing.md` when adding or restructuring Administration Jest tests.
- Read `coding-guidelines/administration/feature-flags-and-deprecations.md` when touching Admin feature flags, deprecations, or BC behavior.
