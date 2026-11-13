---
title: Administration public API snapshot and break check
date: 2026-11-10
area: administration
tags: [administration, public-api, backward-compatibility, extensions, agents]
---

## Context

Coding agents now write most Administration pull requests, and we cannot review all of them by hand.
The main review risk is breaking extensions.
Plugins hook into the Administration through names they only know as strings: components, blocks, methods called via `$super`, props, services, stores, routes and snippet keys.
If a pull request renames or moves one of them, neither the build nor the tests fail, and the plugin stops working.

Three changes of this kind passed review:

- [#20959](https://github.com/shopware/shopware/pull/20959) moved a block into another block and added an element that opens in one block and closes in another.
- [#17339](https://github.com/shopware/shopware/pull/17339) renamed a block only when the major feature flag is on, which was noticed four weeks later.
- [#15271](https://github.com/shopware/shopware/pull/15271) deleted blocks and regenerated `blocks-list.json`, so the guard test passed again.

The generated lists we have today contain names only, and regenerating a list makes its test pass.

## Decision

We generate a static representation of the Administration public API from the source code and commit it.
A pull request that changes the public API shows that change in its diff, and CI fails on unannounced breaks.

### What is public

Everything without `@private` is public, as `technical-docs/11-code-quality/02-unused-code.md` already says.
Requiring `@public` instead would make almost everything private, because only about 50 items carry that tag.

Private parts are in the snapshot too, marked as private, and a change to them is never a break.
A public component that extends a private one has public inherited blocks, because plugins override them by the name of the child.

Some API is tagged `@private` although plugins cannot avoid it.
`Application.getContainer`, `addServiceProvider`, `addServiceProviderDecorator` and the event bus become public.
The functions that every built plugin bundle calls stay out of the plugin-facing types, but the check treats changes to them as breaks.

### The snapshot

- It covers the whole Administration, because a partial snapshot gives false safety: the `Shopware` object with services and stores, the component API, the block tree with its nesting, and routes, snippet keys and registry entries.
- It consists of `.d.ts` files as the contract that TypeScript can check, and a plain-text report per unit that gives readable diffs.
- It exists for two states, the current one and the one with the major feature flag on.
  A break behind the major flag is invisible in a single snapshot.
- It replaces `blocks-list.json`, `position-identifiers.json`, `data-sets.json` and `shopware-modules.json`.

### The checks

Two checks run in CI.
The drift check regenerates the snapshot and fails when the committed one is stale.
The break check runs the generator from trunk on the base and the head commit and blocks unannounced breaks, even when the pull request committed a regenerated snapshot.

A removed, renamed or moved public element is a break.
A new one is an addition, and a change that was announced is allowed in its major.

A break made on purpose needs an entry with a justification in a committed file that has a code owner.
A pull request label cannot accept a break, and coding agents never add such an entry on their own.

### Announcing changes

Planned changes follow the PHP split from `coding-guidelines/core/backward-compatibility.md`.
`@deprecated tag:vX` is for elements that are removed or replaced.
A planned change to an element that stays uses `@bc-change <Kind> version:vX`.
Such a change does not go into `@deprecated`, because the extension ESLint preset reports every use of a deprecated element as an error.

Every kind of change has one pattern, and a finding names it.
The rules are: additive first, core keeps the old way while the flag is off, and both flag states are tested.

The Administration validates its markers with its own TypeScript implementation.
Once that runs in CI, `AnnotationTagTest` stops scanning the Administration app, so each file has one validator.

### Plugins

The snapshot ships with the Administration, and `admin:check-extensions` checks a plugin against it.
With `--target=<major>` it reports, on the current version, every plugin use that the next major breaks.

## Consequences

- A pull request that changes a public name also changes the committed snapshot.
  The author regenerates it, and the reviewer sees the change in the diff.
- A breaking change needs a marker and its pattern, or an accepted entry with a justification.
  That is more work than today.
- The check covers names.
  It does not see behaviour inside a method, visual changes or names built at runtime, so those stay a human judgment.
- Core commits to keeping the API that becomes public stable.
  Other private API that plugins use stays unprotected.
- The generator and the checks are built one kind of unit at a time, starting with blocks.
  Each step documents the format of the part it adds.
  The steps are tracked in [#22591](https://github.com/shopware/shopware/issues/22591).
