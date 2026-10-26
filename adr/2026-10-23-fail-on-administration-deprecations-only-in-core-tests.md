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

### Fail only in core's own Jest run

In Jest runs that load core's test setup, `triggerDeprecationOrThrow` is temporarily changed:

- In the platform's own Jest run, it behaves as before.
- In any other Jest run, it does nothing.

The running Administration does not use this test setup, so its guard still warns, and throws in next-major mode as before.

## Consequences

* Extension Jest runs that reuse core's test setup no longer fail on deprecations that only core can fix.
* These runs do not report deprecations until the check is removed.
  The running Administration, the `@deprecated` annotations, the release notes, and the upgrade notes still tell extension developers about them.
* Core's own Jest run is unchanged and still fails on every unasserted deprecation that the baseline does not list.

## Exit plan

Remove `isPlatformDeprecationRun()`, the marker, and the early return once core's deprecation baseline is stable.
Then extension Jest runs fail on unasserted deprecations too.

Extensions that cannot migrate yet will need to migrate to the new extension tooling and can baseline these deprecations explicitly then.
