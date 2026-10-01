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

`@private` symbols and identifiers starting with `_` are no public contract and need no guard. See the [ADR](../../adr/2026-08-10-administration-javascript-deprecation-guards.md).
