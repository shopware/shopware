---
name: shopware-admin-js
description: Apply Shopware Administration JavaScript, TypeScript, Vue, Pinia, Twig.JS, Jest, linting, and module guidance. Use when editing files under src/Administration/Resources/app/administration or implementing Admin UI behavior.
---

# Shopware Admin JS

Shopware Administration is not a normal Vue app.

## Architecture

- Components are registered through Shopware's component factory instead of Single File Components.
- Twig.JS templates support extension/customization of runtime Vue components.
- The boot sequence initializes state, config, feature flags, dependency injection, and the global `Shopware` object.
- Use the existing local pattern in `src/core`, `src/app`, or `src/module` before introducing a new shape.

## Code

- Use TypeScript for new code.
- Do not introduce public API breaks without prior discussion.
- Follow existing component, module, service, repository, and store patterns.
- For Admin UI that reads or persists DAL entities or associations, update matching ACL privilege mapping and migrations for existing roles when needed.

## Tests And Checks

- Write Jest tests for new features and bug fixes.
- Keep tests next to the code under test with `.spec.ts` when adding new TypeScript tests.
- Split very large specs into a `.spec/` directory by behavior group.
- Use repository composer wrappers for linting, formatting, and Admin unit tests:
  - `composer eslint:admin`
  - `composer stylelint:admin`
  - `composer format:admin`
  - `composer admin:unit`
