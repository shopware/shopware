---
title: Fail on Administration deprecations only in core's own tests
date: 2026-10-23
area: administration
tags: [administration, deprecation, extension-api, jest]
---

## Context

[Administration JavaScript deprecation guards](2026-08-10-administration-javascript-deprecation-guards.md) are supposed to report every use of a deprecated Administration API through `Shopware.Feature.triggerDeprecationOrThrow()`.

The Administration has deprecated usages it needs to fix on its own.
Plugins get deprecations reported that they are not responsible for.
If plugins have Jest tests, these fail and they need to wait for the Administration to fix them.

[PR 21704](https://github.com/shopware/shopware/pull/21704) introduced a baseline, but existing setups need to migrate to the extension tooling's shared Jest setup underway in [PR 21822](https://github.com/shopware/shopware/pull/21822).

## Decision

In Jest runs that load core's test setup, a deprecation that is neither asserted, allowed, nor baselined temporarily fails the test only in the platform's own run:

- In the platform's own Jest run, it fails the test as before.
- In any other Jest run, it does not fail the test.
  The recorder still records it, so `toHaveTriggered()`, `allowDeprecation()`, and majors a test activates work as before.

The platform's own run is recognised by where the spec file lives: inside the platform's `src/`.
The console allow-list keeps its `[Deprecation]` entry until core is migrated.

The running Administration does not use this test setup, so its guard still warns, and throws in next-major mode as before.

## Consequences

* Extension Jest runs that reuse core's test setup no longer fail on deprecations that only core can fix.
* These runs do not fail on unasserted deprecations until the check is removed.
  The running Administration, the `@deprecated` annotations, the release notes, and the upgrade notes still tell extension developers about them.
* Extensions can still assert deprecations with `toHaveTriggered()` and test that their own guards throw with `it.activeFeatureFlags()`.
* Core's own Jest run is unchanged and still fails on every unasserted deprecation that the baseline does not list.

## Exit plan

Remove `isPlatformDeprecationRun()` and its use in `endDeprecationLog()` once core's deprecation baseline is stable.
Remove the `[Deprecation]` entry of the console allow-list then as well.
Then extension Jest runs fail on unasserted deprecations too.

Extensions that cannot fix their deprecated uses yet will need to migrate to the new extension tooling and can baseline these deprecations explicitly then.
