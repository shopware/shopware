---
title: Fail on Administration deprecations only in core's own tests
date: 2026-10-23
area: administration
tags: [administration, deprecation, extension-api, jest]
---

## Context

[Administration JavaScript deprecation guards](2026-08-10-administration-javascript-deprecation-guards.md) report every use of a deprecated Administration API through `Shopware.Feature.triggerDeprecationOrThrow()`, including the `deprecated` option of components and props.
Core still uses many of its own deprecated APIs.
The known usages are listed in `src/meta/deprecation-baseline.json`, and core's Jest run checks them against it.

Many extensions run their Administration specs with their own Jest config, but reuse core's test setup `test/_setup/prepare_environment.js`.
That setup records deprecations and fails a test on every deprecation it neither asserts nor finds in the baseline.
The baseline only covers core's own specs.
So a core component that renders a deprecated core component fails every extension spec that mounts it, although only core can fix the usage.
Routing core's hand-written deprecation notices through the guard failed 45 tests in 9 suites of the SwagCommercial Administration Jest run, most of them on `sw-tabs` inside core components such as `sw-settings-search`.

## Decision

### Fail only in core's own Jest run

Core's `_jest.config.ts` sets the global `__shopwarePlatformDeprecationRun` through Jest `globals`.
An extension's Jest config does not set it.
The private check `isPlatformDeprecationRun()` in `test/_helper_/platformDeprecationRun.ts` reads it.

In core's own run, the recorder is unchanged: assertions, the baseline, its reporter, and the throws of the major lane.
Outside it, the recorder in `test/_setup/_mocks_/feature.service.mock.js` returns at once:

```js
function triggerDeprecationOrThrow(majorFlag, message, options) {
    if (!isPlatformDeprecationRun()) {
        return;
    }

    // record the deprecation, or call the production guard, as before
}
```

It records nothing, fails no test, throws nothing, and warns nothing.
It does not call the production guard either, because the guard's `console.warn` would fail the extension's test through the console guard of the same setup.

### Keep the running Administration unchanged

The production guard in `src/core/feature.ts` stays as it is.
It warns for every deprecation, and throws in next-major mode unless the message is in the baseline.
Extension developers see warnings for core's own usages that they cannot fix.
The console of the Administration already shows many such warnings, so more of them until core is migrated are acceptable noise.

### Why no option and no runtime silence

We considered to also silence the production guard outside core's Jest run.
Notices that warned before the guards existed would then need a private option to stay loud.

1. Extension developers would no longer see new deprecations in the running Administration.
2. Every converted notice would have to remember the option, and the deprecation plugin would have to forward it.
3. Shops would run a temporary branch in production code.

A public feature flag would make the silence a contract that extensions rely on, with its own name, documentation, and deprecation.

The chosen check only lives in the test setup.
Removing it later changes no production code.

## Consequences

* Extension Jest runs that reuse core's test setup no longer fail on deprecations that only core can fix.
* These runs do not report deprecations until the check is removed.
  The running Administration, the `@deprecated` annotations, the release notes, and the upgrade notes still tell extension developers about them.
* Extensions with a fully separate Jest setup do not load core's recorder.
  They reach the production guard and see its warnings like in the running Administration.
* Core's own Jest run is unchanged and still fails on every unasserted deprecation that the baseline does not list.

## Exit plan

Remove `isPlatformDeprecationRun()`, the marker, and the early return once core's deprecation baseline is stable.
Then extension Jest runs fail on unasserted deprecations too.

Extensions that cannot migrate yet keep their own baseline through the extension tooling in `scripts/extensionTooling`.
That needs a configurable baseline path and a baseline reporter that an extension's Jest config can use.
