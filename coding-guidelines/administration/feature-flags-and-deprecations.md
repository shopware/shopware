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
- At the major, remove the flag checks, the legacy branch, obsolete tests, and stale documentation in the same change.
  The major flag itself stays registered in `feature.yaml` (default on, not toggleable), because plugins use it as a version switch.
  Every other flag is removed from `feature.yaml` once it is enabled.

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

For now, only core's own Jest run fails on deprecations.
In the Jest run of an extension that reuses core's test setup, the deprecation recorder stays silent, while the running Administration warns as before.
See the [ADR](../../adr/2026-10-23-fail-on-administration-deprecations-only-in-core-tests.md).

### Deprecation baseline

Jest lets known usages of deprecated APIs pass when they are listed in the deprecation baseline.
In the running Administration, a deprecation whose message is listed in the baseline logs a console error instead of throwing once the major flag is active.
To update it, run both commands:

```bash
DEPRECATION_BASELINE=update composer admin:unit
```

```bash
DEPRECATION_BASELINE=update composer admin:unit:major
```
