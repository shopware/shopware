---
name: shopware-admin-js
description: Apply Shopware Administration JS/TS/Vue coding rules. Use when editing Admin UI code, Jest specs, component behavior, or DAL-backed Admin UI that needs ACL privilege handling.
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
