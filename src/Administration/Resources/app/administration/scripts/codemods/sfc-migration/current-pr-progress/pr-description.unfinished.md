# Missing: PR Description Body Is Empty

**Status:** The "What does this change do, exactly?" section in PR #15673 is blank.

---

## Current state

The PR was opened as a draft and the description body was never filled in. Reviewers and contributors have no context about:

- What problem this PR solves
- How the implementation works at a high level
- What the output of the codemod looks like
- What limitations exist

---

## What to write

The PR description should cover:

### Summary

One paragraph explaining: this PR adds a CLI codemod that migrates Administration components from the `index.js` + `.html.twig` file pair to a single `.vue` SFC, converting Options API to Composition API and Twig blocks to `<sw-block>` elements.

### What the codemod does

- Input: `index.js` (Options API, `Shopware.Component.register`) + `*.html.twig`
- Output: `<name>.vue` with `<template>` and `<script setup>`
- Three outcomes: fully-migrated, partially-migrated (Options API backoff), not-migratable (skipped)
- Blockers: render function (hard), mixins/extends (soft)

### Example

Show a before/after snippet — even a small one like `simple-component` makes the PR immediately understandable.

### How to run it

```bash
npx tsx src/Administration/Resources/app/administration/scripts/codemods/sfc-migration/run-sfc-migration.ts <path>
```

### Known limitations

Brief list (link to `README.md` for full details):
- `<sw-block>` components need to be built separately
- `this.$el` requires manual review
- `mixins`/`extends` require manual migration

---

## Relevant link

PR: shopware/shopware#15673

---

## Acceptance check

- [ ] PR description is filled in with at minimum: summary, how it works, how to run, known limitations
- [ ] A before/after code example is included
- [ ] Description is written before the draft is promoted to "Ready for review"
