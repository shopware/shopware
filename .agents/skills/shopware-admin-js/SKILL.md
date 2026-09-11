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
- Follow existing component, module, service, repository, and store patterns.
- For Admin UI that reads or persists DAL entities or associations, update matching ACL privilege mapping and migrations for existing roles when needed.

## Tests

- Write Jest tests for new features and bug fixes.
- Keep tests next to the code under test with `.spec.ts` when adding new TypeScript tests.
- Split very large specs into a `.spec/` directory by behavior group.
- Optional, after the specs are green: run `npm run mutation:changed` in the Administration root (`-- --list` only prints the targets). It mutation-tests only the JS/TS files changed against `trunk`. Cover surviving mutants with a real assertion or leave them with a short reason when the mutant is equivalent. Twig templates are not mutated, so template behaviour never shows up in the score; the script logic of components does. Files without a spec show up as "no coverage", which means write the spec first.

## Detailed Guidelines

- Read `coding-guidelines/administration/architecture.md` when changing Admin architecture, component registration, services, state, or module patterns.
- Read `coding-guidelines/administration/testing.md` when adding or restructuring Administration Jest tests.
- Read `coding-guidelines/administration/feature-flags-and-deprecations.md` when touching Admin feature flags, deprecations, or BC behavior.
