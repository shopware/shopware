# Administration feature flags and deprecations

Follow the general feature-flag rules in [core feature flags](../core/feature-flags.md). These Administration-specific rules cover JavaScript, Vue, and Admin extension APIs.

## Feature flags

- Use feature flags for temporary rollout or deprecation branches, not as permanent configuration.
- Use uppercase flag names for Administration JavaScript flags, for example `V6_8_0_0` or `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`.
- Keep each flag focused on one behavior or migration path.
- Avoid nested feature flags; they create hard-to-test state combinations.
- Keep the old behavior inside the branch that will be deleted when the flag is removed.
- Test both relevant flag states when both branches are still supported.

## Deprecations

- Mark deprecated Administration APIs with `@deprecated tag:vX.Y.Z - ...` and name the replacement.
- Guard a deprecated public API at runtime where it is consumed, see [Runtime guards](#runtime-guards). `sw-deprecation-rules/require-deprecation-guard` enforces this.
- Document the migration path when deprecating public Administration extension points.
- Do not introduce new internal callers of deprecated APIs; move core/Admin code to the replacement.
- When removing a flag, remove the legacy branch, flag configuration, obsolete tests, and stale documentation in the same change.

### Runtime guards

Call `Shopware.Feature.triggerDeprecationOrThrow()` first thing in a deprecated global API, service method, exported function, or component `methods` / `computed` member. It warns in development builds before the major and throws once the major flag is active:

```ts
Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'sw-example.oldMethod() is deprecated. Use newMethod() instead.');
```

Annotate a deprecated component or prop with the `deprecated` option instead. The deprecation plugin guards the component every time it is created, and the prop every time a parent supplies it:

```js
Component.register('sw-example', {
    deprecated: { version: 'v6.8.0.0', comment: 'Use "mt-example" instead.' },

    props: {
        emptyImagePath: {
            type: String,
            required: false,
            deprecated: { version: 'v6.8.0.0', comment: 'Use "emptyIcon" instead.' },
        },
    },
});
```

Pass a registered major flag and a version like `v6.8.0.0`. Anything else could never throw, so it logs a console error instead.

`@private` symbols and identifiers starting with `_` are no public contract and need no guard.

Types, styles, tests, Twig markup, `data`, store `state` and `getters`, `watch`, `provide` and `inject` stay static-only. Anything else that cannot carry a guard has to record why, on the same annotation:

```ts
/**
 * @deprecated tag:v6.8.0 - Will be removed
 * @deprecationGuard static-only - Module-level import, there is no runtime use boundary.
 */
```

See the [ADR](../../adr/2026-08-10-administration-javascript-deprecation-guards.md).

### Deprecation baseline

In Jest, every deprecation a test runs into fails the test unless the test asserts it. `src/Administration/Resources/app/administration/src/meta/deprecation-baseline.json` lists the known core usages that are not migrated yet, so these pass without an assertion:

```json
{
    "The old usage of \"sw-loader\" is deprecated and will be removed in v6.8.0.0. Please use \"mt-loader\" instead.": {
        "sw-extension-card-bought > sw-loader": [
            "default",
            "major"
        ],
        "sw-select-base > sw-loader": [
            "major"
        ]
    }
}
```

- The key is the deprecation message without the `Used in: …` line.
- A usage site is `<template author> > <component>` when the guard runs inside a component: the component that guards, and the component whose template renders it (`sw-loader` alone when a spec mounts it). Outside a component, it is the path of the file that calls the deprecated function, relative to the Administration, or the spec that calls it.
- Each usage site lists the Jest runs it passes in: `default` (`composer admin:unit`) and `major` (`composer admin:unit:major`, CI job "Jest Admin (major)").
- The running Administration matches the message only: with the major flag active, a listed message logs a console error instead of throwing.
- Never add a usage to make a failing spec pass: migrate it to the replacement instead. Add an entry only on purpose, e.g. when a new guard lands before its callers are migrated.
- A full, green run fails on usages listed for it that no spec needs anymore. Remove the run from them when you migrate a usage.

To regenerate the file, run both commands below, then review the diff. Each run rewrites only its own markings, and only after a full, green run:

```bash
DEPRECATION_BASELINE=update composer admin:unit
DEPRECATION_BASELINE=update composer admin:unit:major
```

A spec that tests a deprecated path on purpose asserts the deprecation instead of listing it:

```ts
import { deprecations } from 'test/_helper_/deprecations';

it('renders the legacy loader', async () => {
    const wrapper = await createWrapper();

    expect(deprecations).toHaveTriggered('The old usage of "sw-loader" is deprecated');
    expect(wrapper.find('.sw-loader').exists()).toBe(true);
});
```
