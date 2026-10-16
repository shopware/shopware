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
- Guard a deprecated public API at runtime where it is consumed, see [Runtime guards](#runtime-guards).
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

`@private` symbols and identifiers starting with `_` are no public contract and need no guard. See the [ADR](../../adr/2026-08-10-administration-javascript-deprecation-guards.md).

### Deprecation baseline

`src/Administration/Resources/app/administration/src/meta/deprecation-baseline.json` lists the known core usages of deprecated APIs that are not migrated yet, so Jest lets them pass without an assertion.
A full, green run fails on usages listed for it that no spec needs anymore.
Remove the run from them when you migrate a usage.

To regenerate the file, run both commands below, then review the diff.
Each run rewrites only its own markings, and only after a full, green run:

```bash
DEPRECATION_BASELINE=update composer admin:unit
DEPRECATION_BASELINE=update composer admin:unit:major
```
