---
title: Jest test files should be JavaScript only
date: 2023-04-14
area: admin
tags: [admin, jest, javascript, typescript]
---

## Context
There is a mix of both JavaScript and TypeScript Jest test files in the Administration.
Respectively `*.spec.js` and `*.spec.ts` files. We want to settle on one format, to keep it uniform.

### Current distribution
There are 46 `*.spec.ts` and 620 `*.spec.js` files.

### Known problems with TypeScript Jest test files
- The TypeScript eslint `no-unused-vars` rule is broken in Jest test files
- There is no type safety for components, because `vue-test-utils` will just type to `any` Vue component
- Several editors loose the Jest context for `*.spec.ts` files
- The Jest config only adds globals to `*.spec.js` files
- TypeScript linting was disabled for `*.spec.ts` files, therefore they are more like `*.spec.js` files

## Decision
Accounting the current distribution and the known problems we face with `*.spec.ts` files, we decided to use `*.spec.js` files from now on.

## Consequences
All existing `*.spec.ts` where moved to `*.spec.js` files and TypeScript specific code was removed.
Additionally to prevent new `*.spec.ts` files an eslint rule was added which prevents new files to be added.
