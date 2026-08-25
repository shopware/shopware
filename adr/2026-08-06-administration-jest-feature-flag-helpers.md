---
title: Use declarative Jest helpers for feature flags in the Administration
date: 2026-08-06
area: administration
tags: [administration, testing, jest, feature-flags]
---

## Context

Administration specs used to control feature flags by mutating a global by hand:

```js
global.activeFeatureFlags = ['v6.8.0.0'];
```

That has three problems.

It is **order dependent**. The array is shared, so a spec that sets it and does not reset it leaks
into the next test. Specs grew defensive `beforeEach`/`afterEach` resets to compensate, and the ones
that forgot were flaky in ways that only showed up when the file order changed.

It is **too late**. Assigning inside a test callback happens after the setup hooks have already run,
so a component mounted in `beforeEach` never sees the flag.

It **cannot express intent**. A test that only makes sense before a major and one that only makes
sense after it look identical, so neither the reader nor the runner can tell them apart. Running the
suite with the major flag on required each spec to have anticipated it.

A second, quieter problem: many specs shadowed the globally registered feature service with a local
mock.

```js
provide: {
    feature: { isActive: (flag) => flag === 'v6.8.0.0' && featureActive },
},
```

A component that injects `feature` then reads a hardcoded boolean instead of the real flag. The test
passes, but it proves nothing about the flag — and any attempt to drive it from the outside silently
does nothing.

## Decision

Feature flags in Administration tests are declared on the test, not assigned inside it.

```js
// Runs with the flag active, in the default suite and the major suite alike.
it.activeFeatureFlags(['v6.8.0.0'])('renders the meteor tabs', async () => { /* ... */ });

// Skipped once the flag is active, because the behaviour it covers is gone by then.
// @deprecated tag:v6.8.0.0 - The test will be removed with the legacy sw-tabs branch.
it.deprecated('v6.8.0.0')('renders the deprecated tabs', async () => { /* ... */ });

// Both support table-driven tests.
it.activeFeatureFlags(['v6.8.0.0']).each(rows)('handles %s', async (row) => { /* ... */ });
```

Three rules follow from this:

1. **Do not assign `global.activeFeatureFlags` in a spec.** The helpers own it. A custom Jest
   environment activates the flags before the setup hooks run and restores the baseline afterwards,
   which is the part a spec cannot do for itself.

2. **Do not provide a local `feature` mock.** The globally registered feature service already reads
   the active flags and normalises both notations (`v6.8.0.0` and `V6_8_0_0`). A local mock hides
   that. Mounting a component with `provide.feature` inside a test that used
   `it.activeFeatureFlags()` now throws, because such a test can only pass for the wrong reason.

3. **`it.deprecated()` means "this disappears with that version"**, not "this currently fails". It
   marks a test whose subject is being removed, and the registered test name gains a
   `(removed in <version>)` suffix so a skipped test explains itself in the reporter output.

## Consequences

Legacy and post-major expectations can live side by side in one file, each stating which world it
belongs to, and both suites can be green at the same time.

Deprecated tests become greppable. `@deprecated tag:v6.8.0` next to `it.deprecated('v6.8.0.0')`
means the major cleanup is a search, not an audit.

The trade is that a test's flag context is no longer visible in its body — you have to read the `it`
line. In exchange it is visible *everywhere*, including in hooks that run before the callback, which
is where the old approach broke.

Specs still carrying manual flag mutation or a local `feature` mock keep working; they are simply not
covered by any of this, and the shadowing check only fires when both styles are combined in one test.
